"""
Training Script — Accident Severity Classifier
------------------------------------------------
Fine-tunes MobileNetV2 on your dataset.

Dataset structure expected:
    dataset/
    ├── moderate/
    ├── normal/
    └── severe/

Run with:
    py -3.11 train.py

Output:
    accident_model.pth  — saved model weights
"""

import torch
import torch.nn as nn
import torch.optim as optim
import torchvision.transforms as transforms
import torchvision.datasets as datasets
from torchvision import models
from torch.utils.data import DataLoader, random_split
import os
import time

# ── Config ────────────────────────────────────────────────────────────────────
DATASET_DIR  = os.path.join(os.path.dirname(__file__), "dataset")
MODEL_OUTPUT = os.path.join(os.path.dirname(__file__), "accident_model.pth")
EPOCHS       = 15
BATCH_SIZE   = 16
LEARNING_RATE = 0.001
VAL_SPLIT    = 0.2   # 20% of data used for validation
IMG_SIZE     = 224
NUM_CLASSES  = 3
# ──────────────────────────────────────────────────────────────────────────────

# Data augmentation for training, simple resize for validation
train_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.RandomHorizontalFlip(),
    transforms.RandomRotation(10),
    transforms.ColorJitter(brightness=0.2, contrast=0.2),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406],
                         std=[0.229, 0.224, 0.225])
])

val_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406],
                         std=[0.229, 0.224, 0.225])
])


def load_data():
    full_dataset = datasets.ImageFolder(root=DATASET_DIR, transform=train_transform)

    # Print class mapping so we know the order
    print(f"\nClass mapping: {full_dataset.class_to_idx}")
    print(f"Total images: {len(full_dataset)}\n")

    # Split into train / validation
    val_size   = int(len(full_dataset) * VAL_SPLIT)
    train_size = len(full_dataset) - val_size
    train_set, val_set = random_split(full_dataset, [train_size, val_size])

    # Apply val transform to validation set
    val_set.dataset.transform = val_transform

    train_loader = DataLoader(train_set, batch_size=BATCH_SIZE, shuffle=True,  num_workers=0)
    val_loader   = DataLoader(val_set,   batch_size=BATCH_SIZE, shuffle=False, num_workers=0)

    return train_loader, val_loader, full_dataset.classes


def build_model():
    model = models.mobilenet_v2(weights=models.MobileNet_V2_Weights.DEFAULT)

    # Freeze all layers first
    for param in model.parameters():
        param.requires_grad = False

    # Replace classifier head — only this will be trained initially
    model.classifier[1] = nn.Linear(model.last_channel, NUM_CLASSES)

    # Unfreeze last few feature layers for fine-tuning
    for param in model.features[-3:].parameters():
        param.requires_grad = True

    return model


def train():
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    print(f"Using device: {device}")

    train_loader, val_loader, classes = load_data()
    print(f"Classes: {classes}")
    print(f"Train batches: {len(train_loader)} | Val batches: {len(val_loader)}\n")

    model     = build_model().to(device)
    criterion = nn.CrossEntropyLoss()
    optimizer = optim.Adam(
        filter(lambda p: p.requires_grad, model.parameters()),
        lr=LEARNING_RATE
    )
    scheduler = optim.lr_scheduler.StepLR(optimizer, step_size=5, gamma=0.5)

    best_val_acc = 0.0

    for epoch in range(1, EPOCHS + 1):
        start = time.time()

        # ── Training phase ──
        model.train()
        train_loss, train_correct, train_total = 0.0, 0, 0

        for images, labels in train_loader:
            images, labels = images.to(device), labels.to(device)

            optimizer.zero_grad()
            outputs = model(images)
            loss    = criterion(outputs, labels)
            loss.backward()
            optimizer.step()

            train_loss    += loss.item() * images.size(0)
            _, predicted   = outputs.max(1)
            train_correct += predicted.eq(labels).sum().item()
            train_total   += labels.size(0)

        # ── Validation phase ──
        model.eval()
        val_loss, val_correct, val_total = 0.0, 0, 0

        with torch.no_grad():
            for images, labels in val_loader:
                images, labels = images.to(device), labels.to(device)
                outputs        = model(images)
                loss           = criterion(outputs, labels)

                val_loss    += loss.item() * images.size(0)
                _, predicted = outputs.max(1)
                val_correct += predicted.eq(labels).sum().item()
                val_total   += labels.size(0)

        train_acc = 100.0 * train_correct / train_total
        val_acc   = 100.0 * val_correct   / val_total
        elapsed   = time.time() - start

        print(f"Epoch [{epoch:2d}/{EPOCHS}] "
              f"Train Loss: {train_loss/train_total:.4f} Acc: {train_acc:.1f}% | "
              f"Val Loss: {val_loss/val_total:.4f} Acc: {val_acc:.1f}% | "
              f"Time: {elapsed:.1f}s")

        # Save best model
        if val_acc > best_val_acc:
            best_val_acc = val_acc
            torch.save(model.state_dict(), MODEL_OUTPUT)
            print(f"  ✓ Best model saved (val acc: {val_acc:.1f}%)")

        scheduler.step()

    print(f"\nTraining complete. Best validation accuracy: {best_val_acc:.1f}%")
    print(f"Model saved to: {MODEL_OUTPUT}")


if __name__ == "__main__":
    train()
