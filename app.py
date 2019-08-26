from Crypto import Random
from flask import Flask, request, render_template
from flask_wtf import FlaskForm
from wtforms import StringField, PasswordField, ValidationError
from wtforms.validators import DataRequired, EqualTo, Length
import secrets 
import subprocess
import sys

import invite_manager

app = Flask(__name__)

app.config['SECRET_KEY'] = bytes(Random.get_random_bytes(32))

PATH = "invites"

class RegistrationForm(FlaskForm):
    username = StringField('Username', validators=[DataRequired()])
    password = PasswordField('Password', validators=[DataRequired(), EqualTo('confirm', message='Passwords must match.'), Length(min=0, max=256)])
    confirm = PasswordField('Confirm')
    invite = StringField('Invite Code', validators=[DataRequired()])

#TODO: I have no idea what usernames are valid and what aren't so this is a placeholder that prevents you from making your usename rm -r /
    def validate_username(form, field):
        if not field.data.isalnum() or not field.data[0].isalpha():
            print("Username invalid!")
            raise ValidationError("Please use an alphanumeric username not starting with a number")

def check_invite(invite):
    print("Invite %s" % invite)
    if (invite_manager.validate_invite(invite)):
        print("Invite valid!")
        return True
    else:
        print("Invalid invite!")
        return False

def register_user(username, password):
    registered = False
    register_subprocess = subprocess.Popen(["sh", "./register_user.sh", username, password])
    register_streamdata = register_subprocess.communicate()[0]
    register_returncode = register_subprocess.returncode
    print("Registration return code: %d" % register_returncode)
    if (register_returncode == 0):
        registered = True
    return registered

@app.route('/', methods=['POST'])
def registration_submission():
    form = RegistrationForm(request.form)
    invalid_invite = False
    registered = False

    if form.validate_on_submit():
        if check_invite(form.invite.data):
            print("Registering user...")        
            try:
                registered = register_user(form.username.data, str(secrets.randbits(128))) 
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
    print(form.errors)
    return render_template('register.html', form=form, registration={'registered': registered, 'invalid_invite': invalid_invite}) 

@app.route('/', methods=['GET'])
def registration():
    form = RegistrationForm(request.form)
    return render_template('register.html', form=form)
