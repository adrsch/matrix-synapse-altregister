to use:
pip -r requirements.txt
flask run

then navigate to 127.0.0.1:5000

how it does stuff:
app.py contains code for the site using flask. good for testing and nginx can be used to serve flask stuff os for live. flask-wtf is used for the form thingy. the page in teh templates folder is rendered when the page is laopded and the stuff from the flask-wtf form is put into the static content as the tags inside it indicate. submitting sends a post request which calls teh same functin right now, and the if form.validate_on_submit() thing will check to see if its a valid form submission.

invites_manager.py has stuff for handlinginvite codes. right now it just uses uuid4 for the codes. the timestamp is in seconds and i havent tested whether that works at all yet lol. 

generate_invites.py will make a new invite that expires after a week (if the untested expiration system works)

config tells the invite manager where to look for the file containing all the invites and their expirations.
