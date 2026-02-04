#!/bin/bash
set -e

PLUGIN_NAME="findajob-jobs-searcher"
ZIP_NAME="${PLUGIN_NAME}.zip"

# Remove existing zip
if [ -f "$ZIP_NAME" ]; then
    rm "$ZIP_NAME"
fi

echo "Creating $ZIP_NAME..."

# Create a temporary build directory
BUILD_DIR="build_tmp"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

# Copy specific files and directories
cp -r assets includes templates LICENSE README.md readme.txt findajob-jobs-searcher.php "$BUILD_DIR/$PLUGIN_NAME/"

# Cleanup exclusions in the build folder (e.g. if we copied assets, we might want to remove source maps or python scripts if they were there, but currently assets/ contains only css/js and empty index.php)
cd "$BUILD_DIR/$PLUGIN_NAME"

# Remove any accidental dev files
rm -f package.sh generate_assets.py capture_screenshots.py
rm -rf assets-wp-repo

# Remove python caches if they somehow got in
find . -name "__pycache__" -type d -exec rm -rf {} +

cd ../..

# Zip the build folder
cd "$BUILD_DIR"
zip -r "../$ZIP_NAME" "$PLUGIN_NAME"
cd ..

# Cleanup build folder
rm -rf "$BUILD_DIR"

echo "Done! $ZIP_NAME created."
unzip -l "$ZIP_NAME"
