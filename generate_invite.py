import invite_manager
import argparse

def invite_generator():
	invite_manager.clear_expired_invites()
	parser = argparse.ArgumentParser()
	parser.add_argument("--seconds", dest="exp_s", type=int, default=0, help="expiration time in seconds")
	parser.add_argument("--days", dest="exp_d", type=int, default=0, help="expiration time in days")
	args = parser.parse_args()
	#86400 is a day in seconds
	print("Creating an invite expiring in %d days and %s seconds" % (args.exp_d, args.exp_s))
	expiration_time = args.exp_d * 86400 + args.exp_s
	
	#invites lasting more than a week not allowed	
	if (expiration_time > 86400 * 7):
		print("Expiration time must be no longer than a week. Using a week instead")
		expiration_time = 86400 * 7
	invite_manager.generate_invite(expiration_time)

if __name__ == "__main__":
	invite_generator()
