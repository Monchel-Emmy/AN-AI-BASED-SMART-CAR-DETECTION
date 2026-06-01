/**
 * ============================================================
 * Smart Car Accident Detection System
 * ============================================================
 * Hardware:
 *   - ESP32-CAM (AI Thinker)
 *   - LIS3DH at 0x18 (accelerometer on GY-83)
 *   - HMC5883L at 0x1E (compass on GY-83)
 *   - Neo-6M GPS via Serial
 *   - LCD 16x2 via I2C (0x27)
 *   - Red LED GPIO2 | Blue LED GPIO4
 *
 * Wiring:
 *   GY-83 → SDA:GPIO14 | SCL:GPIO15 | VCC:3.3V | GND:GND
 *   GPS   → TX→GPIO12  | RX→GPIO13
 *   LEDs  → GPIO2 (Red) | GPIO4 (Blue)
 * ============================================================
 */

// esp_camera MUST be first — avoids sensor_t conflict
#include "esp_camera.h"

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <QMC5883LCompass.h>
#include <LiquidCrystal_I2C.h>
#include <TinyGPSPlus.h>
#include <HardwareSerial.h>

// ── WiFi ──────────────────────────────────────────────────────
#define WIFI_SSID     "AQUOS sense7 plus"
#define WIFI_PASSWORD "Freethings."

// ── Server URLs ───────────────────────────────────────────────
#define AI_SERVER_URL  "http://10.54.3.125:5000/predict"
#define PHP_SERVER_URL "http://10.54.3.125/accident/web/api/accident/report.php"
#define DEVICE_CODE    "DEV-001"

// ── Pins ──────────────────────────────────────────────────────
#define RED_LED_PIN    2
#define BLUE_LED_PIN   4
#define I2C_SDA        14
#define I2C_SCL        15
#define GPS_RX_PIN     12
#define GPS_TX_PIN     13

// ── LIS3DH ────────────────────────────────────────────────────
#define LIS3DH_ADDR    0x18
#define LIS3DH_SCALE   0.001f   // ±2g, 12-bit: 1mg per LSB

// ── Detection threshold ───────────────────────────────────────
// Requires IMPACT_CONFIRM consecutive readings above threshold
// to avoid false triggers from sensor glitches/noise.
// At rest = 0. Gentle shake ≈ 9. Hard impact > 20.
#define IMPACT_THRESHOLD  15.0  // m/s² above baseline
#define IMPACT_CONFIRM    3     // consecutive samples needed (300ms at 100ms loop)
#define COOLDOWN_MS       3000

// ── Camera Pins (AI Thinker ESP32-CAM) ───────────────────────
#define PWDN_GPIO_NUM   32
#define RESET_GPIO_NUM  -1
#define XCLK_GPIO_NUM    0
#define SIOD_GPIO_NUM   26
#define SIOC_GPIO_NUM   27
#define Y9_GPIO_NUM     35
#define Y8_GPIO_NUM     34
#define Y7_GPIO_NUM     39
#define Y6_GPIO_NUM     36
#define Y5_GPIO_NUM     21
#define Y4_GPIO_NUM     19
#define Y3_GPIO_NUM     18
#define Y2_GPIO_NUM      5
#define VSYNC_GPIO_NUM  25
#define HREF_GPIO_NUM   23
#define PCLK_GPIO_NUM   22

// ── Objects ───────────────────────────────────────────────────
QMC5883LCompass   compass;
LiquidCrystal_I2C lcd(0x27, 16, 2);
TinyGPSPlus       gps;
HardwareSerial    gpsSerial(2);

// ── Calibration state (set at boot) ──────────────────────────
int16_t offX = 0, offY = 0, offZ = 0;
float   restingMag = 0;

unsigned long lastAccidentTime = 0;

// Previous accel values for derived rotation estimate
float prevAx = 0, prevAy = 0, prevAz = 0;
unsigned long prevTime = 0;
int impactCount = 0;  // consecutive readings above threshold

// ─────────────────────────────────────────────────────────────
// LIS3DH — init registers
// ─────────────────────────────────────────────────────────────
void initLIS3DH() {
    // CTRL_REG1: 100Hz ODR, all axes enabled
    Wire.beginTransmission(LIS3DH_ADDR);
    Wire.write(0x20); Wire.write(0x57);
    Wire.endTransmission();

    // CTRL_REG4: ±2g, high resolution, block data update
    Wire.beginTransmission(LIS3DH_ADDR);
    Wire.write(0x23); Wire.write(0x88);
    Wire.endTransmission();

    delay(100);
}

// ─────────────────────────────────────────────────────────────
// LIS3DH — read raw values
// ─────────────────────────────────────────────────────────────
void readRawLIS3DH(int16_t &rx, int16_t &ry, int16_t &rz) {
    Wire.beginTransmission(LIS3DH_ADDR);
    Wire.write(0x28 | 0x80);
    Wire.endTransmission(false);
    Wire.requestFrom(LIS3DH_ADDR, 6, true);
    rx = (Wire.read() | (Wire.read() << 8)) >> 4;
    ry = (Wire.read() | (Wire.read() << 8)) >> 4;
    rz = (Wire.read() | (Wire.read() << 8)) >> 4;
}

// ─────────────────────────────────────────────────────────────
// LIS3DH — read calibrated acceleration in m/s²
// ─────────────────────────────────────────────────────────────
void readLIS3DH(float &ax, float &ay, float &az) {
    int16_t rx, ry, rz;
    readRawLIS3DH(rx, ry, rz);
    ax = (rx - offX) * LIS3DH_SCALE * 9.81f;
    ay = (ry - offY) * LIS3DH_SCALE * 9.81f;
    az = (rz - offZ) * LIS3DH_SCALE * 9.81f;
}

// ─────────────────────────────────────────────────────────────
// AUTO-CALIBRATE — samples resting position at boot
// Works regardless of mounting angle or orientation
// ─────────────────────────────────────────────────────────────
void calibrateLIS3DH() {
    lcdPrint("Calibrating...", "Keep still! 3s");
    Serial.println("[CAL] Sampling resting position...");
    delay(3000);  // give user time to place device

    long sumX = 0, sumY = 0, sumZ = 0;
    const int SAMPLES = 200;

    for (int i = 0; i < SAMPLES; i++) {
        int16_t rx, ry, rz;
        readRawLIS3DH(rx, ry, rz);
        sumX += rx;
        sumY += ry;
        sumZ += rz;
        delay(5);
    }

    offX = sumX / SAMPLES;
    offY = sumY / SAMPLES;
    offZ = sumZ / SAMPLES;

    // Compute resting magnitude (should be ~0 after offset removal)
    float ax, ay, az;
    readLIS3DH(ax, ay, az);
    restingMag = sqrt(ax*ax + ay*ay + az*az);

    Serial.printf("[CAL] Offsets: X=%d Y=%d Z=%d | RestingMag: %.3f m/s²\n",
                  offX, offY, offZ, restingMag);
    lcdPrint("Calibrated!", "");
    delay(1000);
}

// ─────────────────────────────────────────────────────────────
// SETUP
// ─────────────────────────────────────────────────────────────
void setup() {
    Serial.begin(115200);

    pinMode(RED_LED_PIN,  OUTPUT);
    pinMode(BLUE_LED_PIN, OUTPUT);
    digitalWrite(RED_LED_PIN,  LOW);
    digitalWrite(BLUE_LED_PIN, LOW);

    Wire.begin(I2C_SDA, I2C_SCL);

    // LCD
    lcd.init();
    lcd.backlight();
    lcdPrint("Accident Detect", "  System v2.0");
    delay(2000);

    // LIS3DH init + auto-calibrate
    lcdPrint("Init LIS3DH...", "");
    initLIS3DH();
    Serial.println("[OK] LIS3DH ready");
    calibrateLIS3DH();  // samples current position as baseline

    // Compass — set mode for HMC5883L
    lcdPrint("Init Compass...", "");
    compass.init();
    compass.setMode(0x00, 0x70, 0x20, 0x00);  // continuous, 8 samples, 15Hz, ±1.3Ga
    Serial.println("[OK] Compass ready");

    // Camera
    lcdPrint("Init Camera...", "");
    if (!initCamera()) {
        lcdPrint("Camera FAILED", "Check wiring!");
        Serial.println("[ERROR] Camera failed");
        blinkLed(RED_LED_PIN, 5, 200);
        while (1) delay(100);
    }
    Serial.println("[OK] Camera ready");

    // GPS
    gpsSerial.begin(9600, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
    Serial.println("[OK] GPS started");

    // WiFi
    lcdPrint("Connecting WiFi", WIFI_SSID);
    connectWiFi();

    lcdPrint("System Ready", "Monitoring...");
    digitalWrite(BLUE_LED_PIN, HIGH);
    Serial.println("[OK] All systems ready");
    Serial.printf("[OK] Impact threshold: %.1f m/s² above baseline\n", IMPACT_THRESHOLD);
}

// ─────────────────────────────────────────────────────────────
// MAIN LOOP
// ─────────────────────────────────────────────────────────────
void loop() {
    // GPS
    while (gpsSerial.available()) gps.encode(gpsSerial.read());

    // LIS3DH
    float ax, ay, az;
    readLIS3DH(ax, ay, az);
    float accelMag = sqrt(ax*ax + ay*ay + az*az);

    // How much above resting baseline
    float impact = accelMag - restingMag;

    // Derived rotation estimate — rate of change of acceleration (rad/s approx)
    unsigned long now = millis();
    float dt = (prevTime > 0) ? (now - prevTime) / 1000.0f : 0.1f;
    float gx = (ax - prevAx) / dt;
    float gy = (ay - prevAy) / dt;
    float gz = (az - prevAz) / dt;
    prevAx = ax; prevAy = ay; prevAz = az;
    prevTime = now;

    // Compass
    compass.read();
    int heading = compass.getAzimuth();

    // LCD
    char line1[17], line2[17];
    snprintf(line1, sizeof(line1), "A:%.1f H:%d", impact, heading);
    snprintf(line2, sizeof(line2), "%s", gps.location.isValid() ? "GPS:OK" : "GPS:searching");
    lcdPrint(line1, line2);

    Serial.printf("Impact:%.2f m/s² (raw:%.2f) | Heading:%d° | GPS:%s\n",
                  impact, accelMag, heading,
                  gps.location.isValid() ? "OK" : "searching");

    // ── Accident Detection ────────────────────────────────────
    if ((now - lastAccidentTime) > COOLDOWN_MS) {
        if (impact > IMPACT_THRESHOLD) {
            impactCount++;
            Serial.printf("[WATCH] Impact count: %d/%d (%.2f m/s²)\n",
                          impactCount, IMPACT_CONFIRM, impact);
        } else {
            impactCount = 0;  // single low reading resets counter
        }

        if (impactCount >= IMPACT_CONFIRM) {
            impactCount      = 0;
            lastAccidentTime = now;

            Serial.printf("\n[ALERT] Accident confirmed! Impact:%.2f m/s²\n", impact);
            lcdPrint("!! ACCIDENT !!", "Processing...");
            blinkLed(RED_LED_PIN, 3, 150);

            float lat = gps.location.isValid() ? gps.location.lat() : 0.0;
            float lng = gps.location.isValid() ? gps.location.lng() : 0.0;

            // Step 1: AI classification
            lcdPrint("AI Analysis...", "Sending image..");
            String severity = getAISeverity();
            Serial.printf("[AI] Severity: %s\n", severity.c_str());
            showSeverity(severity);

            // Step 2: Send report
            lcdPrint("Sending report", "");
            bool sent = sendAccidentReport(severity, lat, lng,
                                           ax, ay, az,
                                           heading);

            lcdPrint(sent ? "Report sent!" : "Send FAILED", severity.c_str());
            Serial.println(sent ? "[OK] Report sent" : "[ERROR] Report failed");

            delay(5000);
            lcdPrint("System Ready", "Monitoring...");
            digitalWrite(RED_LED_PIN,  LOW);
            digitalWrite(BLUE_LED_PIN, HIGH);
        }
    }

    delay(100);
}

// ─────────────────────────────────────────────────────────────
// CAMERA INIT
// ─────────────────────────────────────────────────────────────
bool initCamera() {
    camera_config_t config;
    config.ledc_channel = LEDC_CHANNEL_0;
    config.ledc_timer   = LEDC_TIMER_0;
    config.pin_d0       = Y2_GPIO_NUM;
    config.pin_d1       = Y3_GPIO_NUM;
    config.pin_d2       = Y4_GPIO_NUM;
    config.pin_d3       = Y5_GPIO_NUM;
    config.pin_d4       = Y6_GPIO_NUM;
    config.pin_d5       = Y7_GPIO_NUM;
    config.pin_d6       = Y8_GPIO_NUM;
    config.pin_d7       = Y9_GPIO_NUM;
    config.pin_xclk     = XCLK_GPIO_NUM;
    config.pin_pclk     = PCLK_GPIO_NUM;
    config.pin_vsync    = VSYNC_GPIO_NUM;
    config.pin_href     = HREF_GPIO_NUM;
    config.pin_sscb_sda = SIOD_GPIO_NUM;
    config.pin_sscb_scl = SIOC_GPIO_NUM;
    config.pin_pwdn     = PWDN_GPIO_NUM;
    config.pin_reset    = RESET_GPIO_NUM;
    config.xclk_freq_hz = 20000000;
    config.pixel_format = PIXFORMAT_JPEG;
    config.frame_size   = FRAMESIZE_QVGA;
    config.jpeg_quality = 12;
    config.fb_count     = 1;
    return esp_camera_init(&config) == ESP_OK;
}

// ─────────────────────────────────────────────────────────────
// AI SEVERITY — captures image, sends to Flask, returns severity
// Also encodes image to base64 for PHP report
// ─────────────────────────────────────────────────────────────
String capturedImageB64 = "";  // global — holds base64 for PHP after AI call

String getAISeverity() {
    capturedImageB64 = "";  // reset
    if (WiFi.status() != WL_CONNECTED) return "normal";

    camera_fb_t *fb = esp_camera_fb_get();
    if (!fb) { Serial.println("[ERROR] Camera capture failed"); return "normal"; }

    // ── Encode to base64 for PHP (only if small enough) ──────
    if (fb->len < 40000) {
        const char *chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
        String b64 = "";
        b64.reserve((fb->len / 3 + 1) * 4 + 4);
        int i = 0;
        uint8_t *data = fb->buf;
        size_t len    = fb->len;
        while (i < (int)len) {
            uint32_t a = i < (int)len ? data[i++] : 0;
            uint32_t b = i < (int)len ? data[i++] : 0;
            uint32_t c = i < (int)len ? data[i++] : 0;
            uint32_t t = (a << 16) | (b << 8) | c;
            b64 += chars[(t >> 18) & 0x3F];
            b64 += chars[(t >> 12) & 0x3F];
            b64 += chars[(t >>  6) & 0x3F];
            b64 += chars[(t >>  0) & 0x3F];
        }
        int mod = len % 3;
        if (mod == 1) { b64[b64.length()-2] = '='; b64[b64.length()-1] = '='; }
        if (mod == 2) { b64[b64.length()-1] = '='; }
        capturedImageB64 = b64;
        Serial.printf("[Camera] Encoded %d bytes → %d base64 chars\n", fb->len, b64.length());
    }

    // ── Send raw JPEG to AI ───────────────────────────────────
    HTTPClient http;
    http.begin(AI_SERVER_URL);
    http.setTimeout(20000);

    String boundary  = "----ESP32Boundary";
    String bodyStart = "--" + boundary + "\r\n"
                     + "Content-Disposition: form-data; name=\"image\"; filename=\"accident.jpg\"\r\n"
                     + "Content-Type: image/jpeg\r\n\r\n";
    String bodyEnd   = "\r\n--" + boundary + "--\r\n";

    http.addHeader("Content-Type", "multipart/form-data; boundary=" + boundary);

    size_t totalSize = bodyStart.length() + fb->len + bodyEnd.length();
    uint8_t *body    = (uint8_t *)malloc(totalSize);
    String severity  = "normal";

    if (body) {
        memcpy(body,                                bodyStart.c_str(), bodyStart.length());
        memcpy(body + bodyStart.length(),           fb->buf,           fb->len);
        memcpy(body + bodyStart.length() + fb->len, bodyEnd.c_str(),   bodyEnd.length());

        int httpCode = http.POST(body, totalSize);
        free(body);

        if (httpCode == 200) {
            String response = http.getString();
            StaticJsonDocument<512> doc;
            if (!deserializeJson(doc, response))
                severity = doc["severity"].as<String>();
            Serial.printf("[AI Response] %s\n", response.c_str());
        } else {
            Serial.printf("[AI] HTTP error: %d\n", httpCode);
        }
    } else {
        Serial.println("[ERROR] malloc failed");
    }

    esp_camera_fb_return(fb);
    http.end();
    return severity;
}

// ─────────────────────────────────────────────────────────────
// SEND REPORT TO PHP
// ─────────────────────────────────────────────────────────────
bool sendAccidentReport(
    const String &severity,
    float lat, float lng,
    float ax, float ay, float az,
    int heading
) {
    if (WiFi.status() != WL_CONNECTED) {
        connectWiFi();
        if (WiFi.status() != WL_CONNECTED) return false;
    }

    HTTPClient http;
    http.begin(PHP_SERVER_URL);
    http.setTimeout(20000);
    http.addHeader("Content-Type", "application/json");

    // DynamicJsonDocument to handle variable image base64 size
    DynamicJsonDocument doc(65536);
    doc["device_code"] = DEVICE_CODE;
    doc["severity"]    = severity;
    doc["latitude"]    = lat;
    doc["longitude"]   = lng;
    doc["accel_x"]     = ax;
    doc["accel_y"]     = ay;
    doc["accel_z"]     = az;
    // No gyroscope — store impact magnitude and compass as useful substitutes
    float impactMag = sqrt(ax*ax + ay*ay + az*az);
    doc["gyro_x"]      = impactMag;          // total acceleration magnitude
    doc["gyro_y"]      = (float)heading;     // compass heading in degrees
    doc["gyro_z"]      = impactMag - restingMag;  // net impact above baseline

    // Include image if captured successfully
    if (capturedImageB64.length() > 0) {
        doc["image_base64"] = capturedImageB64;
    }

    String payload;
    serializeJson(doc, payload);

    int httpCode = http.POST(payload);
    String response = http.getString();
    http.end();

    Serial.printf("[PHP] HTTP %d: %s\n", httpCode, response.c_str());
    return (httpCode == 201);
}

// ─────────────────────────────────────────────────────────────
// SEVERITY DISPLAY
// ─────────────────────────────────────────────────────────────
void showSeverity(const String &severity) {
    digitalWrite(RED_LED_PIN,  LOW);
    digitalWrite(BLUE_LED_PIN, LOW);

    if (severity == "urgent") {
        lcdPrint("!! URGENT !!", "Call Emergency!");
        for (int i = 0; i < 10; i++) {
            digitalWrite(RED_LED_PIN, HIGH); delay(150);
            digitalWrite(RED_LED_PIN, LOW);  delay(150);
        }
        digitalWrite(RED_LED_PIN, HIGH);
    } else if (severity == "medium") {
        lcdPrint("MEDIUM Accident", "Alert sent");
        for (int i = 0; i < 5; i++) {
            digitalWrite(RED_LED_PIN, HIGH); delay(400);
            digitalWrite(RED_LED_PIN, LOW);  delay(400);
        }
        digitalWrite(BLUE_LED_PIN, HIGH);
    } else {
        lcdPrint("Normal incident", "Logged only");
        blinkLed(BLUE_LED_PIN, 3, 300);
        digitalWrite(BLUE_LED_PIN, HIGH);
    }
}

// ─────────────────────────────────────────────────────────────
// WIFI
// ─────────────────────────────────────────────────────────────
void connectWiFi() {
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("[WiFi] Connecting");
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500); Serial.print("."); attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
        Serial.printf("\n[WiFi] Connected: %s\n", WiFi.localIP().toString().c_str());
        lcdPrint("WiFi Connected", WiFi.localIP().toString().c_str());
        blinkLed(BLUE_LED_PIN, 2, 200);
    } else {
        Serial.println("\n[WiFi] FAILED");
        lcdPrint("WiFi FAILED", "Check settings");
        blinkLed(RED_LED_PIN, 3, 300);
    }
    delay(1000);
}

// ─────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────
void lcdPrint(const char *line1, const char *line2) {
    lcd.clear();
    lcd.setCursor(0, 0); lcd.print(line1);
    lcd.setCursor(0, 1); lcd.print(line2);
}

void blinkLed(int pin, int times, int delayMs) {
    for (int i = 0; i < times; i++) {
        digitalWrite(pin, HIGH); delay(delayMs);
        digitalWrite(pin, LOW);  delay(delayMs);
    }
}
