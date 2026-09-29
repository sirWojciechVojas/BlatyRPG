#!/bin/sh
set -eu

# Named volumes and legacy bind mounts can retain Alpine's www-data UID/GID
# (82) after moving the runtime to Debian (www-data UID 33). Make every
# existing storage directory group-writable, then keep that group on newly
# created descendants via the setgid bit.
storage_group="${APP_STORAGE_GROUP:-app-env-read}"

for storage_path in \
    /var/www/html/writable/uploads/handouts \
    /var/www/html/writable/uploads/audio
do
    mkdir -p "$storage_path"
    chgrp -R "$storage_group" "$storage_path"
    chmod -R g+rwX "$storage_path"
    find "$storage_path" -type d -exec chmod g+s {} +
done

# Keep future private-upload directories writable by the shared storage group.
umask 0007

exec docker-php-entrypoint "$@"
