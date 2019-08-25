from Crypto import Random
from flask import Flask, request, render_template
from flask_wtf import FlaskForm
from wtforms import StringField, PasswordField, ValidationError
from wtforms.validators import DataRequired, EqualTo, Length

import invite_manager

app = Flask(__name__)

app.config['SECRET_KEY'] = bytes(Random.get_random_bytes(32))

PATH = "invites"

class RegistrationForm(FlaskForm):
    username = StringField('Username', validators=[DataRequired()])
    password = PasswordField('Password', validators=[DataRequired(), EqualTo('confirm', message='Passwords must match.'), Length(min=0, max=256)])
    confirm = PasswordField('Confirm')
    invite = StringField('Invite Code')

    def validate_username(form, field):
        #TODO: look up username in database, see if it exists
        pass

    def validate_invite(form, field):
        print(field.data)
        if (invite_manager.validate_invite(field.data)):
            print("Invite valid!")
        else:
            print("Invalid invite!")
            raise ValidationError("Invalid invite!")

def register_user(username, password):
    registered = False
    return True

@app.route('/', methods=['GET', 'POST'])
def registration():
    form = RegistrationForm(request.form)
    if form.validate_on_submit():
        print("registering user")
        try:
            registered = register_user(form.username.data, form.password.data)
            invite_manager.remove_invite_from_tmp(form.invite.data)
        except ValidationError:
            print("There was an error in registration. Restoring invite...")
            invite_manager.restore_invite(form.invite.data)
    return render_template('register.html', form=form)
