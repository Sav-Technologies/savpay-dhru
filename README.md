# SavPay Dhru Module

The official **SavPay Payment Integration Module for Dhru Fusion**, developed by **Sav Technologies**.  
This module enables Dhru Fusion users to accept payments through SavPay, including mobile banking methods such as Bkash, Nagad, Rocket, Upay, Tap, and 30+ Bangladeshi banks, as well as international payment solutions.

---

## 🚀 Features

- ✔ Seamless SavPay integration for Dhru Fusion
- ✔ Automatic payment verification
- ✔ Supports Bkash, Nagad, Rocket, Upay, Tap
- ✔ Supports 30+ Bangladeshi Banks
- ✔ Supports international payment options (e.g., Binance)
- ✔ Secure API communication
- ✔ Fast and lightweight module
- ✔ Easy installation
- ✔ Clean UI for customers and admins

---

## 📦 Installation

1. Download the module ZIP file.
2. Upload the module folder to your Dhru Fusion installation:

/modules/payment/savpay/

3. Go to your **Dhru Admin Panel**.
4. Navigate to:

Settings → Gateway Settings

5. Enable **SavPay**.
6. Enter your:
   - **API Key**
7. Save settings.

Your Dhru system is now ready to receive payments via SavPay.

---

## ⚙️ Configuration

Inside Dhru Admin Panel → Gateways Settings:

| Field | Description |
|-------|-------------|
| API Key | Secret key for secured requests |
| Currency | Set to your store currency |
| Conversion Rate | Set the BDT=USD Rate |
| Enable/Disable | Activate or deactivate module |

---

## 🔐 Security

SavPay Dhru module uses:

- Encrypted API communication  
- Token verification  
- Verified transaction callbacks  
- No sensitive data stored on your server  

SavPay only processes **payment-related transaction information**.

---

## ❗ Troubleshooting

### Payment not showing?
- Ensure the API key is correct  
- Verify server SSL is installed

### Transaction not verified?
- Dhru server blocked outgoing requests  
- Check firewall/CURL/SSL  
- Reconfirm API Keys in the SavPay dashboard

### Module does not appear?
- Ensure folder structure is correct:

modules/payment/savpay/

---

## 🙋 Support

For support or integration help:

**Sav Technologies**  
🌍 Website: https://sav.com.bd  
📧 Email: hello@sav.com.bd  
🏢 Dhaka, Bangladesh

---

## 👨‍💻 Developer

**Imam Hasan Emon**  
Lead Developer – Sav Technologies  
GitHub: https://github.com/imamhasanemonbd

---

## 📜 License

Copyright © 2025 Sav Technologies
Author: Imam Hasan Emon
All Rights Reserved.

You are allowed to use this module with Dhru Fusion.
Modification, redistribution, or reverse engineering is not permitted without permission.

---

## ⭐ Support Us

If this module helps you, please give it a **⭐ star** on GitHub and support Sav Technologies.

