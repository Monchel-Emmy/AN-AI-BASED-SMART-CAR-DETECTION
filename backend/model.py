"""
Accident severity classifier using MobileNetV2.

Classes match the dataset folder names:
    moderate → medium severity
    normal   → no accident / minor
    severe   → urgent / critical

When accident_model.pth exists, real inference is used.
Otherwise falls back to simulation mode for testing.
"""

import torch
import torchvision.transforms as transforms
from torchvision import models
from PIL import Image
import numpy as np
import io
import os

# ── Class config ──────────────────────────────────────────────────────────────
# Order MUST match what ImageFolder assigned during training.
# ImageFolder sorts alphabetically: moderate=0, normal=1, severe=2
LABELS = ["moderate", "normal", "severe"]

# Map dataset labels → API severity response
SEVERITY_MAP = {
    "moderate": "medium",
    "normal":   "normal",
    "severe":   "urgent"
}
# ──────────────────────────────────────────────────────────────────────────────

TRANSFORM = transforms.Compose([
    transforms.Resize((224, 224)),
    transforms.ToTensor(),
    transforms.Normalize(
        mean=[0.485, 0.456, 0.406],
        std=[0.229, 0.224, 0.225]
    )
])

MODEL_PATH = os.path.join(os.path.dirname(__file__), "accident_model.pth")


def load_model():
    model = models.mobilenet_v2(weights=models.MobileNet_V2_Weights.DEFAULT)
    model.classifier[1] = torch.nn.Linear(model.last_channel, len(LABELS))

    if os.path.exists(MODEL_PATH):
        model.load_state_dict(torch.load(MODEL_PATH, map_location="cpu"))
        print(f"[Model] Loaded trained weights from {MODEL_PATH}")
    else:
        print("[Model] No trained weights found — running in simulation mode.")
        print("[Model] Run 'py -3.11 train.py' to train on your dataset.")

    model.eval()
    return model


_model = load_model()


def predict(image_bytes: bytes) -> dict:
    """
    Predict accident severity from raw image bytes.

    Returns:
        {
            "severity":   "urgent" | "medium" | "normal",
            "confidence": float,
            "scores": {
                "urgent":  float,
                "medium":  float,
                "normal":  float
            }
        }
    """
    image  = Image.open(io.BytesIO(image_bytes)).convert("RGB")
    tensor = TRANSFORM(image).unsqueeze(0)

    with torch.no_grad():
        if os.path.exists(MODEL_PATH):
            outputs       = _model(tensor)
            probabilities = torch.softmax(outputs, dim=1)[0]
        else:
            probabilities = _simulate_prediction(image)

    # Map raw class scores → severity labels
    raw_scores = {
        label: round(float(prob), 4)
        for label, prob in zip(LABELS, probabilities)
    }

    # Convert to severity-named scores for the API response
    severity_scores = {
        SEVERITY_MAP[label]: raw_scores[label]
        for label in LABELS
    }

    # Pick the highest scoring class
    best_label    = max(raw_scores, key=raw_scores.get)
    severity      = SEVERITY_MAP[best_label]
    confidence    = raw_scores[best_label]

    return {
        "severity":   severity,
        "confidence": confidence,
        "scores":     severity_scores
    }


def _simulate_prediction(image: Image.Image) -> torch.Tensor:
    """
    Simulation mode: brightness-based pseudo-prediction.
    Dark images → severe, mid → moderate, bright → normal.
    """
    grayscale  = np.array(image.convert("L"), dtype=np.float32)
    brightness = grayscale.mean() / 255.0

    # Order: [moderate, normal, severe]
    if brightness < 0.35:
        probs = [0.15, 0.05, 0.80]   # severe
    elif brightness < 0.60:
        probs = [0.65, 0.20, 0.15]   # moderate
    else:
        probs = [0.20, 0.75, 0.05]   # normal

    return torch.tensor(probs)
