import argparse
import subprocess

def fetch_arguments():
    parser = argparse.ArgumentParser()
    parser.add_argument("username", type=str)
    parser.add_argument("hashed_password", type=str)
    args = parser.parse_args()
    return args

def change_password(args):
    change_password_subprocess = subprocess.Popen(["sh", "./change_password_hash.sh", args.username, args.hashed_password])
    change_password_streamdata = change_password_subprocess.communicate()[0]
    change_password_returncode = change_password_subprocess.returncode
    print("Password updated return code: %d" % (change_password_returncode))

if __name__ == "__main__":
    args = fetch_arguments()
    change_password(args)
