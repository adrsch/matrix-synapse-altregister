import configparser
import os
from uuid import uuid4
import time
import secrets

SEPARATOR = " |Expiration (seconds since epoch):| "

#reads config file ./config to get the path for invites to be stored in
def get_invites_path():
    config = configparser.ConfigParser()
    config.read("config")
    try:
        return config["Invites"]["path"]
    except (KeyError, ValueError):
        print("Error reading config file! Using default location ./invites.dat")        
        return "invites.dat"

def generate_code():
    config = configparser.ConfigParser()
    config.read("config")
    try:
        words = config["Invites"]["words"]
        with open(words, "r") as f:
            wordlist = [word for word in f.readlines()]
    except (KeyError, ValueError, IOError):
        print("Error reading wordlist specified in config file! Cannot find words for generating an invite with!")
        raise SystemExit
    assert len(wordlist) == 2048
    phrase = [wordlist[secrets.randbits(11)].strip() for i in range(int(config["Invites"]["length"]))]
    return " ".join(phrase)

#deletes all expired invites in invites file
def clear_expired_invites():
    path = get_invites_path()
    try:
        with open(path, "r") as f:
            invites = f.readlines()
    except IOError:
        return
    with open(path, "w+") as f:
        for invite in invites:
            try:
                if int(invite.strip().split(SEPARATOR)[1]) > int(time.time()):
                    f.write(invite)
                else:
                    print("Deleting an old, expired invite...")
            except ValueError:
                print("ERROR: Invalid invite present in invites file.")
                raise SystemExit

def validate_invite(code):
    valid = False;
    try:
        with open(get_invites_path(), "r") as f:
            invites = f.readlines()
    except IOError:
        return valid
    with open(get_invites_path(), "w+") as f:
        for invite in invites:
            invite_data = invite.strip().split(SEPARATOR)
            if invite_data[0] == code and int(invite_data[1]) > int(time.time()):
                valid = True
                with open((get_invites_path() + ".tmp"), "a+") as tmp:
                    tmp.write(invite)
            else:
                f.write(invite)
    return valid


def remove_invite_from_tmp(code):
    removed = False;
    tmp_path = get_invites_path() + ".tmp"
    try:
        with open(tmp_path, "r") as f:
            invites = f.readlines()
    except IOError:
        return restored
    with open(tmp_path, "w+") as f:
        for invite in invites:
            invite_data = invite.strip().split(SEPARATOR)
            if invite_data[0] == code:
                removed = True
            else:
                f.write(invite)
    return removed

def restore_invite(code):
    restored = False;
    tmp_path = get_invites_path() + ".tmp"
    try:
        with open(tmp_path, "r") as f:
            invites = f.readlines()
    except IOError:
        return restored
    with open(tmp_path, "w+") as f:
        for invite in invites:
            invite_data = invite.strip().split(SEPARATOR)
            if invite_data[0] == code and int(invite_data[1]) > int(time.time()):
                restored = True
                with open(get_invites_path(), "a+") as invites_file:
                    invites_file.write(invite)
                print("Restored invite from temporary file into normal invite file")
            else:
                f.write(invite)
    return restored

#generate an invite that expires in time_to_expire seconds. the invites file format is "code expiration_time_in_seconds"
def generate_invite(time_to_expire):
    code = generate_code()  
    with open(get_invites_path(), "a") as f:
        f.write("%s%s%d\n" % (code, SEPARATOR, int(time.time()) + time_to_expire))
        print("New code: %s" % code)

