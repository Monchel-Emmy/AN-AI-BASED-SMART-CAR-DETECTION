# Hardware Wiring Guide

## Components
- ESP32-CAM (AI Thinker)
- MPU6050 (Accelerometer + Gyroscope)
- Neo-6M GPS Module
- LCD 16x2 with I2C backpack
- Red LED
- Blue LED
- 2x 220Ω resistors (for LEDs)

---

## Wiring Table

### MPU6050 → ESP32-CAM
| MPU6050 Pin | ESP32-CAM Pin |
|-------------|---------------|
| VCC         | 3.3V          |
| GND         | GND           |
| SDA         | GPIO 14       |
| SCL         | GPIO 15       |
| AD0         | GND (address 0x68) |

### LCD 16x2 (I2C backpack) → ESP32-CAM
| LCD Pin | ESP32-CAM Pin |
|---------|---------------|
| VCC     | 5V            |
| GND     | GND           |
| SDA     | GPIO 14       |
| SCL     | GPIO 15       |

> MPU6050 and LCD share the same I2C bus (SDA/SCL)

### Neo-6M GPS → ESP32-CAM
| GPS Pin | ESP32-CAM Pin |
|---------|---------------|
| VCC     | 3.3V          |
| GND     | GND           |
| TX      | GPIO 12       |
| RX      | GPIO 13       |

### Red LED → ESP32-CAM
| Connection         | Pin     |
|--------------------|---------|
| LED (+) → 220Ω → | GPIO 2  |
| LED (-)            | GND     |

### Blue LED → ESP32-CAM
| Connection         | Pin     |
|--------------------|---------|
| LED (+) → 220Ω → | GPIO 4  |
| LED (-)            | GND     |

---

## I2C Address Check
If LCD doesn't work, its I2C address might be 0x3F instead of 0x27.
Change this line in the code:
```cpp
LiquidCrystal_I2C lcd(0x27, 16, 2);
// to:
LiquidCrystal_I2C lcd(0x3F, 16, 2);
```

---

## Arduino IDE Setup

### 1. Install ESP32 Board
- File → Preferences → Additional Board URLs:
  ```
  https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
  ```
- Tools → Board Manager → search "esp32" → Install

### 2. Select Board
- Tools → Board → ESP32 Arduino → **AI Thinker ESP32-CAM**

### 3. Install Libraries (Tools → Manage Libraries)
- `Adafruit MPU6050`
- `Adafruit Unified Sensor`
- `LiquidCrystal I2C` by Frank de Brabander
- `TinyGPSPlus` by Mikal Hart
- `ArduinoJson` by Benoit Blanchon

### 4. Upload Settings
- Tools → Upload Speed: **115200**
- Tools → Port: your COM port
- Connect GPIO 0 to GND before uploading, disconnect after

---

## Before Uploading — Edit These in the .ino file

```cpp
#define WIFI_SSID     "YOUR_WIFI_NAME"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"
#define AI_SERVER_URL  "http://YOUR_PC_IP:5000/predict"
#define PHP_SERVER_URL "http://YOUR_PC_IP/accident/api/accident/report.php"
#define DEVICE_CODE   "DEV-001"
```

Find your PC IP: open CMD → type `ipconfig` → look for IPv4 Address

---

## System Flow

```
Power ON
   ↓
Init MPU6050, Camera, GPS, LCD, WiFi
   ↓
Loop: Read accelerometer + gyroscope every 100ms
   ↓
Impact detected? (accel > 15 m/s² OR gyro > 5 rad/s)
   ↓
Capture image → Send to Python AI → Get severity
   ↓
Show on LCD + blink LEDs based on severity
   ↓
Send full report to PHP backend (sensor data + GPS + image)
   ↓
PHP stores in DB + sends WhatsApp alert
   ↓
Wait 30 seconds → Resume monitoring
```

---

## Severity → LED Behavior

| Severity | Red LED         | Blue LED | LCD Message       |
|----------|-----------------|----------|-------------------|
| urgent   | Fast blink → ON | OFF      | !! URGENT !!      |
| medium   | Slow blink      | ON       | MEDIUM Accident   |
| normal   | OFF             | Blink    | Normal incident   |
