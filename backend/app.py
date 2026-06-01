"""
Accident Detection Backend
--------------------------
Flask server that receives an image and returns accident severity prediction.

Endpoints:
    POST /predict  — Upload image, get severity classification
    GET  /health   — Health check
"""

from flask import Flask, request, jsonify
from model import predict
import traceback

app = Flask(__name__)

# Max upload size: 10 MB
app.config["MAX_CONTENT_LENGTH"] = 10 * 1024 * 1024

ALLOWED_EXTENSIONS = {"jpg", "jpeg", "png", "bmp", "webp"}


def allowed_file(filename: str) -> bool:
    return (
        "." in filename
        and filename.rsplit(".", 1)[1].lower() in ALLOWED_EXTENSIONS
    )


@app.route("/health", methods=["GET"])
def health():
    """Simple health check endpoint."""
    return jsonify({"status": "ok", "message": "Accident detection backend is running"}), 200


@app.route("/predict", methods=["POST"])
def predict_accident():
    """
    Predict accident severity from an uploaded image.

    Request:
        Content-Type: multipart/form-data
        Body: image file under the key "image"

    Response (200):
        {
            "severity": "urgent" | "medium" | "normal",
            "confidence": 0.85,
            "scores": {
                "normal": 0.05,
                "medium": 0.10,
                "urgent": 0.85
            },
            "message": "Accident classified as urgent. Emergency alert triggered."
        }

    Response (400): Missing or invalid file
    Response (500): Internal server error
    """

    # --- Validate request ---
    if "image" not in request.files:
        return jsonify({
            "error": "No image provided.",
            "hint": "Send the image as form-data with key 'image'"
        }), 400

    file = request.files["image"]

    if file.filename == "":
        return jsonify({"error": "Empty filename. Please select a file."}), 400

    if not allowed_file(file.filename):
        return jsonify({
            "error": f"Unsupported file type.",
            "allowed_types": list(ALLOWED_EXTENSIONS)
        }), 400

    # --- Run prediction ---
    try:
        image_bytes = file.read()

        if len(image_bytes) == 0:
            return jsonify({"error": "Uploaded file is empty."}), 400

        result = predict(image_bytes)

        # Build human-readable message based on severity
        severity = result["severity"]
        messages = {
            "urgent": "Accident classified as URGENT. Emergency alert triggered.",
            "medium": "Accident classified as MEDIUM severity. Monitoring required.",
            "normal": "Accident classified as NORMAL. No immediate action needed."
        }

        return jsonify({
            "severity": severity,
            "confidence": result["confidence"],
            "scores": result["scores"],
            "message": messages[severity]
        }), 200

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "error": "Prediction failed.",
            "details": str(e)
        }), 500


if __name__ == "__main__":
    print("Starting Accident Detection Backend...")
    print("Test it at:   ")
    app.run(debug=True, host="0.0.0.0", port=5000)
