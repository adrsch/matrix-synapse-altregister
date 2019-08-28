#!/bin/sh
#arguments: 1 username 2 password 
register_new_matrix_user -c /etc/matrix-synapse/homeserver.yaml http://localhost:8008 --no-admin -u $1 -p $2
#note that this is untested ^
registration_exit_code=$?
echo $registration_exit_code
if [ $registration_exit_code != 0 ]
then
	echo Registration failed!
else
	echo Registration successful!
fi

exit $registration_exit_code
