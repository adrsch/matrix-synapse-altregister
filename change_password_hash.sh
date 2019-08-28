#!/bin/sh
#arguments: 1 username 2 password (pre-hashed)
sqlite3 /var/lib/matrix-synapse/homeserver.db "UPDATE users SET password_hash='$2' WHERE name='@$1:uotechcollective.org';"
exit $?
