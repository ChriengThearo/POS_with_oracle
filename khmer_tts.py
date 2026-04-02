import asyncio
import sys
import os
import ctypes
import tempfile
import edge_tts


async def speak(text: str, output_file: str):
    communicate = edge_tts.Communicate(text=text, voice="km-KH-SreymomNeural")
    await communicate.save(output_file)


def play_mp3(file_path: str):
    """Play an MP3 file using Windows built-in MCI (no extra libraries needed)."""
    winmm = ctypes.windll.winmm
    winmm.mciSendStringW(f'open "{file_path}" type mpegvideo alias bakong_audio', None, 0, None)
    winmm.mciSendStringW('play bakong_audio wait', None, 0, None)
    winmm.mciSendStringW('close bakong_audio', None, 0, None)


def format_amount(amount_str: str, currency: str) -> str:
    """Format amount for natural Khmer speech."""
    try:
        amount = float(amount_str)
        if currency == "KHR":
            return str(int(amount))
        else:
            # Remove trailing zeros for USD, e.g. 1.20 -> 1.20, 1.00 -> 1
            if amount == int(amount):
                return str(int(amount))
            return f"{amount:.2f}"
    except ValueError:
        return amount_str


if __name__ == "__main__":
    amount_str = sys.argv[1] if len(sys.argv) > 1 else "0"
    currency = sys.argv[2].upper() if len(sys.argv) > 2 else "KHR"
    debt_str = sys.argv[3] if len(sys.argv) > 3 else "0"

    formatted = format_amount(amount_str, currency)

    try:
        debt = float(debt_str)
    except ValueError:
        debt = 0.0

    if currency == "USD":
        if debt > 0:
            formatted_debt = format_amount(str(round(debt, 2)), currency)
            text = f"បានទទួល {formatted} ដុល្លារ និងជំពាក់ {formatted_debt} ដុល្លារ"
        else:
            text = f"បានទទួល {formatted} ដុល្លារ សូមអរគុណ!"
    else:
        if debt > 0:
            formatted_debt = format_amount(str(round(debt, 2)), currency)
            text = f"បានទទួល {formatted} រៀល និងជំពាក់ {formatted_debt} រៀល"
        else:
            text = f"បានទទួល {formatted} រៀល សូមអរគុណ!"

    output_file = os.path.join(tempfile.gettempdir(), "bakong_receive.mp3")

    asyncio.run(speak(text, output_file))
    play_mp3(output_file)
