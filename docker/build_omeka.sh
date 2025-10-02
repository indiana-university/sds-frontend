#!/bin/bash

# Script to download, customize, and package Omeka-S
# Based on task.md requirements

set -e  # Exit on error

OMEKA_VERSION="4.1.1"
OMEKA_ZIP="omeka-s-${OMEKA_VERSION}.zip"
OMEKA_DIR="omeka-s"
OMEKA_URL="https://github.com/omeka/omeka-s/releases/download/v${OMEKA_VERSION}/${OMEKA_ZIP}"
CUSTOM_SOURCE="../omeka/SDS_2_0/omekaS_v_4_1_1"
OUTPUT_ZIP="omeka-s.zip"

echo "=== Omeka-S Build Script ==="
echo "Version: ${OMEKA_VERSION}"
echo ""

# Step 1: Download Omeka-S
echo "Step 1: Downloading Omeka-S ${OMEKA_VERSION}..."
if [ -f "${OMEKA_ZIP}" ]; then
    echo "  - ${OMEKA_ZIP} already exists, skipping download"
else
    curl -L -o "${OMEKA_ZIP}" "${OMEKA_URL}"
    echo "  - Download complete"
fi

# Step 2: Unzip
echo ""
echo "Step 2: Extracting ${OMEKA_ZIP}..."
if [ -d "${OMEKA_DIR}" ]; then
    echo "  - Removing existing ${OMEKA_DIR} directory"
    rm -rf "${OMEKA_DIR}"
fi
unzip -q "${OMEKA_ZIP}"
echo "  - Extraction complete"

# Step 3: Replace folders with custom versions
echo ""
echo "Step 3: Replacing folders with customized versions..."

# Check if custom source directory exists
if [ ! -d "${CUSTOM_SOURCE}" ]; then
    echo "ERROR: Custom source directory not found: ${CUSTOM_SOURCE}"
    exit 1
fi

# Define folders to replace
folders=(
    "application/asset/css"
    "application/view/common"
    "application/view/layout"
    "application/view/omeka"
    "themes/default"
)

# Replace each folder
for folder in "${folders[@]}"; do
    echo "  - Replacing ${folder}..."
    
    # Check if source folder exists
    if [ ! -d "${CUSTOM_SOURCE}/${folder}" ]; then
        echo "    WARNING: Source folder not found: ${CUSTOM_SOURCE}/${folder}"
        continue
    fi
    
    # Remove existing folder
    if [ -d "${OMEKA_DIR}/${folder}" ]; then
        rm -rf "${OMEKA_DIR}/${folder}"
    fi
    
    # Create parent directory if needed
    mkdir -p "$(dirname "${OMEKA_DIR}/${folder}")"
    
    # Copy custom folder
    cp -r "${CUSTOM_SOURCE}/${folder}" "${OMEKA_DIR}/${folder}"
    echo "    ✓ Replaced ${folder}"
done

# Step 4: Create final zip
echo ""
echo "Step 4: Creating ${OUTPUT_ZIP}..."
if [ -f "${OUTPUT_ZIP}" ]; then
    echo "  - Removing existing ${OUTPUT_ZIP}"
    rm -f "${OUTPUT_ZIP}"
fi
zip -q -r "${OUTPUT_ZIP}" "${OMEKA_DIR}"
echo "  - Created ${OUTPUT_ZIP}"

# Cleanup
echo ""
echo "Cleaning up temporary files..."
rm -rf "${OMEKA_DIR}"
rm -f "${OMEKA_ZIP}"

echo ""
echo "=== Build Complete ==="
echo "Output: ${OUTPUT_ZIP}"
