import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

MAIL_HOST = "smtp.office365.com"
MAIL_PORT = 587
MAIL_USERNAME = "no-reply@diplomasi.app"
MAIL_PASSWORD = "ELAF123elaf123"
MAIL_FROM_ADDRESS = "no-reply@diplomasi.app"
MAIL_FROM_NAME = "Diplomasi"
TO_ADDRESS = "ahmed.albakor206@gmail.com"

subject = "Diplomasi Test Email"
body = "This is a test email from Diplomasi (no-reply)."

msg = MIMEMultipart()
msg["From"] = f"{MAIL_FROM_NAME} <{MAIL_FROM_ADDRESS}>"
msg["To"] = TO_ADDRESS
msg["Subject"] = subject
msg.attach(MIMEText(body, "plain"))

with smtplib.SMTP(MAIL_HOST, MAIL_PORT) as server:
    server.ehlo()
    server.starttls()
    server.ehlo()
    server.login(MAIL_USERNAME, MAIL_PASSWORD)
    server.sendmail(MAIL_FROM_ADDRESS, TO_ADDRESS, msg.as_string())

print("Email sent successfully")