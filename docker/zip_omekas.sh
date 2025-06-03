#!/bin/bash
# Copy the Omeka S directory
echo "Copying Omeka S files..."
cp -pr ../omeka/SDS_2_0/omekaS_v_4_1_1/ omeka-s

# Create empty database.ini file
echo "Creating empty database configuration file..."
touch omeka-s/config/database.ini

# Check if zip file already exists and remove it
if [ -f omeka-s.zip ]; then
    echo "Removing existing omeka-s.zip file..."
    rm omeka-s.zip
fi

# Create zip archive of the Omeka S directory
echo "Creating zip archive..."
zip -r omeka-s.zip omeka-s

# Delete the omeka-s folder after zip creation
echo "Removing temporary omeka-s directory..."
rm -rf omeka-s

echo "Build process completed successfully!"