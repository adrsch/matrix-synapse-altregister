from flask import Flask, request, render_template
from flask_wtf import FlaskForm
from wtforms import StringField, PasswordField, ValidationError, TextAreaField, HiddenField
from wtforms.validators import DataRequired, EqualTo, Length
import secrets 
import subprocess
import sys

import invite_manager

app = Flask(__name__)

app.config['SECRET_KEY'] = secrets.token_urlsafe(32)

PATH = "invites"

class RegistrationForm(FlaskForm):
    #TODO: set length min and max for username and password. 
    username = StringField('Username', validators=[DataRequired()])
    password = HiddenField()
    confirmed = HiddenField()
    #password = PasswordField('Password', validators=[DataRequired(), EqualTo('confirm', message='Passwords must match.'), Length(min=0, max=256)])
    #confirm = PasswordField('Confirm')
    invite = TextAreaField('Invite Code', validators=[DataRequired()])

#TODO: I have no idea what usernames are valid and what aren't so this is a placeholder that prevents you from being too insane. if an invalid username slips past ie it's taken, the error message the user gets will be generic so it's best to catch as much as possible here.
    def validate_username(form, field):
        if not field.data.isalnum() or not field.data[0].isalpha():
            print("Username invalid!")
            raise ValidationError("Please use an alphanumeric username not starting with a number")

#there's probably a way to use a hidden booleanfield which would be a lot cleaner
    def validate_confirmed(form, field):
        if (field.data != "Yes"):
            raise ValidationError("Passwords must match.")

def check_invite(invite):
    if (invite_manager.validate_invite(invite)):
        print("Invite valid!")
        return True
    else:
        print("Invalid invite!")
        return False

def register_user(username, password):
    registered = False
    #register_subprocess = subprocess.Popen(["sh", "./register_user.sh", username, str(secrets.randbits(128))])
    register_subprocess = subprocess.Popen(["sh", "./register_user.sh", username, password])
    register_streamdata = register_subprocess.communicate()[0]
    register_returncode = register_subprocess.returncode
    print("Registration return code: %d" % register_returncode)
    if (register_returncode == 0):
        registered = True
    return registered

@app.route('/', methods=['GET', 'POST'])
def registration_submission():
    form = RegistrationForm(request.form)
    registration_attempted = False
    invalid_invite = False
    registered = False
    
    if form.validate_on_submit():
        registration_attempted = True
        #TODO: Before final version, delete printing the hashed pass, that's still a security flaw.
        print("Registration attempt:\nUsername: %s\nPassword: %s\nInvite: %s" % (form.username.data, form.password.data, form.invite.data))
        if check_invite(form.invite.data):
            print("Registering user...")        
            try:
                registered = register_user(form.username.data, form.password.data) 
            except Exception as e:
                print("There was an error in registration.", file=sys.stderr)
                print(e, file=sys.stderr)
                registered = False
            if registered:
                print("Removing invite from pool...")
                invite_manager.remove_invite_from_tmp(form.invite.data)
            else:
                print("Restoring invite...")
                invite_manager.restore_invite(form.invite.data)
        else:
            invalid_invite = True
    return render_template('register.html', form=form, registration={'registered': registered, 'invalid_invite': invalid_invite, 'registration_attempted': registration_attempted}) 

