import invite_manager

if __name__ == "__main__":
	invite_manager.clear_expired_invites()
	#this is 7 days as 86400 is a day in seconds
	invite_manager.generate_invite(86400 * 7)
