import hmac
import base64
from datetime import datetime


def generate_signature(
    merchant_public_key: str,
    order_amount: float,
    order_currency: str,
    order_merchant_reference_id: str,
    api_password: str,
    timestamp: str,
) -> str:
    """توليد التوقيع بنفس منطق PHP (HMAC-SHA256 ثم Base64)."""
    amount_str = f"{order_amount:.2f}"
    data = f"{merchant_public_key}{amount_str}{order_currency}{order_merchant_reference_id}{timestamp}"
    signature = hmac.new(
        api_password.encode("utf-8"),
        data.encode("utf-8"),
        "sha256",
    ).digest()
    return base64.b64encode(signature).decode("utf-8")


def get_formatted_timestamp() -> str:
    """إرجاع التاريخ والوقت بالصيغة: 2/21/2024 5:16:48 AM"""
    now = datetime.now()
    date_part = f"{now.month}/{now.day}/{now.year}"
    time_part = now.strftime("%I:%M:%S %p")
    if time_part.startswith("0"):
        time_part = time_part[1:]  # 5:16:48 AM بدل 05:16:48 AM
    return f"{date_part} {time_part}"


if __name__ == "__main__":
    # مثال استخدام
    merchant_public_key = "87006fc3-e1f5-4698-9037-7fd7b160017c"
    order_amount = 100.50
    order_currency = "SAR"
    order_merchant_reference_id = "REF-12345"
    api_password = "YOUR_API_PASSWORD"

    timestamp = get_formatted_timestamp()
    signature = generate_signature(
        merchant_public_key,
        order_amount,
        order_currency,
        order_merchant_reference_id,
        api_password,
        timestamp,
    )

    print("Timestamp:", timestamp)
    print("Signature:", signature)
