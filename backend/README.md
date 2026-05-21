# Accident Detection Backend

Flask backend that receives an image and classifies accident severity as **urgent**, **medium**, or **normal**.

---

## Setup

### 1. Install Python dependencies

```bash
pip install -r requirements.txt
```

### 2. Run the server

```bash
python app.py
```

Server starts at: `http://127.0.0.1:5000`

---

## API Endpoints

### GET `/health`
Check if the server is running.

**Response:**
```json
{
  "status": "ok",
  "message": "Accident detection backend is running"
}
```

---

### POST `/predict`
Upload an image to get accident severity prediction.

**Request:**
- Method: `POST`
- Content-Type: `multipart/form-data`
- Body key: `image` → your image file (jpg, jpeg, png, bmp, webp)

**Response:**
```json
{
  "severity": "urgent",
  "confidence": 0.85,
  "scores": {
    "normal": 0.05,
    "medium": 0.10,
    "urgent": 0.85
  },
  "message": "Accident classified as URGENT. Emergency alert triggered."
}
```

---

## Testing with Postman

### Health Check
1. Method: `GET`
2. URL: `http://127.0.0.1:5000/health`
3. Click **Send**

### Predict Accident Severity
1. Method: `POST`
2. URL: `http://127.0.0.1:5000/predict`
3. Go to **Body** tab → select **form-data**
4. Add a key: `image` → change type from **Text** to **File**
5. Upload any accident image (jpg/png)
6. Click **Send**

---

## Severity Logic (Simulation Mode)

Since no trained model is loaded yet, the backend uses image brightness to simulate predictions:

| Brightness | Severity |
|------------|----------|
| Dark image | urgent   |
| Medium     | medium   |
| Bright     | normal   |

To use a real trained model, place your weights file at:
```
backend/accident_model.pth
```
The server will automatically load it on startup.

---

## File Structure

```
backend/
├── app.py              # Flask server + API routes
├── model.py            # AI model loading + prediction logic
├── requirements.txt    # Python dependencies
├── accident_model.pth  # (optional) Your trained model weights
└── README.md           # This file
```
