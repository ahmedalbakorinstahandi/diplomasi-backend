import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

MAIL_HOST = "smtp.ionos.de"
MAIL_PORT = 587
MAIL_USERNAME = "rechnungen@evo-elektriker.de"
MAIL_PASSWORD = "RamiEVO*Erfolg2026"
MAIL_FROM_ADDRESS = "rechnungen@evo-elektriker.de"
MAIL_FROM_NAME = "Evo Elektriker"
TO_ADDRESS = "ahmed.albakor206@gmail.com"

subject = "Evo Elektriker Test Email"
body = "This is a test email via IONOS SMTP (TLS)."

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
