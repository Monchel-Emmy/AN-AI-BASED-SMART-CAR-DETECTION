# Accident Detection — PHP API

## Setup

### 1. Copy to Laragon
Place the entire `web/` folder inside Laragon's `www/` directory:
```
C:\laragon\www\accident\
```

### 2. Create the database
- Open Laragon → HeidiSQL (or phpMyAdmin at http://localhost/phpmyadmin)
- Run the file: `api/database/schema.sql`

### 3. Configure WhatsApp
Edit `api/config/whatsapp.php`:
- Set your phone number
- Set your CallMeBot API key

---

## API Endpoints

### POST `/api/accident/report.php`
Receives accident data from the ESP32 device.

**Body (JSON):**
```json
{
  "device_code":   "DEV-001",
  "severity":      "urgent",
  "latitude":      -1.9441,
  "longitude":     30.0619,
  "accel_x":       2.45,
  "accel_y":      -1.12,
  "accel_z":       9.81,
  "gyro_x":        0.03,
  "gyro_y":        0.01,
  "gyro_z":       -0.02,
  "image_base64":  "..."
}
```

**Response:**
```json
{
  "success":     true,
  "accident_id": 1,
  "severity":    "urgent",
  "driver":      "Jean Pierre",
  "plate":       "RAB 001 A",
  "alert_sent":  true,
  "message":     "Accident recorded successfully"
}
```

---

### GET `/api/accident/list.php`
Returns recent accidents.

**Query params:**
- `?severity=urgent`
- `?device_code=DEV-001`
- `?limit=20&page=1`

---

## Testing with Postman

### Report an accident:
1. Method: `POST`
2. URL: `http://localhost/accident/api/accident/report.php`
3. Body → raw → JSON:
```json
{
  "device_code": "DEV-001",
  "severity":    "urgent",
  "latitude":    -1.9441,
  "longitude":   30.0619,
  "accel_x":     2.45,
  "accel_y":    -1.12,
  "accel_z":     9.81,
  "gyro_x":      0.03,
  "gyro_y":      0.01,
  "gyro_z":     -0.02
}
```

### List accidents:
1. Method: `GET`
2. URL: `http://localhost/accident/api/accident/list.php`
