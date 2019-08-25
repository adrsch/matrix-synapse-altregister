to use:
pip -r requirements.txt
flask run

then navigate to 127.0.0.1:5000

how it does stuff:


app.py contains code for the site using flask. good for testing and nginx can be used to serve flask stuff os for live. flask-wtf is used for the form thingy. the page in teh templates folder is rendered when the page is laopded and the stuff from the flask-wtf form is put into the static content as the tags inside it indicate. submitting sends a post request which calls teh same functin right now, and the if form.validate_on_submit() thing will check to see if its a valid form submission.

config tells the invite manager where to look for the file containing all the invites and their expirations.

invites_manager.py has stuff for handling invite codes. there is a temp file created after an invite is deemed valid so double-registering with 1 invite is less of a possibility. its still a possibility unless the invite file can be locked as it could have one instance read it, the other read it, then one validate and write and the other validate and write, but this would probably be extremely hard to do. might be good in the future to put a lock on the file for the read until the write is finished. also the tmp file, in the case of an exception, has the code restored from it to the normal invites file, as a guard against invites getting swallowed.

generate_invites.py will make a new invite that expires as specified. seconds has been tested but days has not because thats a pretty long time.


