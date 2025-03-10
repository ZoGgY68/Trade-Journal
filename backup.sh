#!/bin/bash

# Navigate to the parent directory
cd /var/www

# Create a compressed archive of the project directory
tar -czvf Trade-Journal-2-backup.tar.gz Trade-Journal-2

echo "Backup completed: /var/www/Trade-Journal-2-backup.tar.gz"
