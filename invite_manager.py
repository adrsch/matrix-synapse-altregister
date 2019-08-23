import configparser
import os
from uuid import uuid4
import time

#reads config file ./config to get the path for invites to be stored in
def get_invites_path():
	config = configparser.ConfigParser()
	config.read("config")
	try:
		return config["Invites"]["path"]
	except (KeyError, ValueError):
		print("Error reading config file! Using default location ./invites.dat")
	return "invites.dat"

#deletes all expired invites in invites file
def clear_expired_invites():
	path = get_invites_path()
	with open(path, "r") as f:
		invites = f.readlines()
	with open(path, "w+") as f:
		for invite in invites:
			if float(invite.strip().split()[1]) > time.time():
				f.write(invite)
def validate_invite(code):
	with open(get_invites_path(), "r") as f:
		invites = f.readlines()
		for invite in invites:
			invite_data = invite.strip().split()
			if invite_data[0] == code and float(invite_data[1]) > time.time():
				return True
	return False
#generate an invite that expires in time_to_expire seconds. the invites file format is "code time_to_expire_in_seconds"
def generate_invite(time_to_expire):
	code = uuid4() #this should be replaced with something prettier 
	with open(get_invites_path(), "a") as f:
		f.write("%s %f\n" % (code, time.time() + time_to_expire))
		print("New code: %s" % code)

