
# NetGuard Emergency

**Real-Time Network-Powered Emergency Response System for Uganda** 

NetGuard Emergency is a mobile and web-based alert system designed to slash emergency response times in regions with inconsistent connectivity. By leveraging **Nokia CAMARA (Open Gateway) APIs**, NetGuard transforms the mobile network into an active tool for pinpointing victims and ensuring critical data reaches responders without delay.

## 🏥 The Problem

In Uganda, emergency response is often critically slow, especially regarding road accidents involving boda-bodas and maternal health complications. Current challenges include:

* 
**Location Ambiguity**: GPS failures in rural areas or low-signal zones.


* 
**Network Congestion**: Vital alerts getting lost in standard traffic.


* 
**Connectivity Gaps**: Difficulty reaching victims when mobile data is unreliable.



## 🚀 The Solution

NetGuard enables bystanders or patients to trigger emergencies instantly via a one-tap app interface or USSD code. The system intelligently routes these alerts to the nearest Village Health Team (VHT), clinic, or trained responder.

### 📡 Nokia CAMARA API Integration (The Trust & Speed Stack)

NetGuard leverages the **Network as Code Developer Portal** to provide a coherent "API Story":

| API | Implementation Purpose |
| --- | --- |
| **Device Location Retrieval** | Provides accurate network-based location (superior to GPS in low-signal areas).

 |
| **Device Reachability Status** | Automatically detects poor data connectivity and triggers an SMS fallback.

 |
| **Quality on Demand (QoD)** | Requests prioritized network quality (low latency) for critical alert transmission.

 |
| **SIM Swap API** | **Security Layer**: Detects recent SIM changes to flag potential fraudulent alerts. |
| **KYC (Know Your Customer)** | **Identity Layer**: Verifies victim details to provide responders with verified identity information. |

---

## 🛠 Technology Stack

* 
**Frontend/Mobile**: Vue.js + Framework7 (Native-like UI) + Pinia.


* 
**Backend**: Laravel 11 (PHP) with Sanctum authentication.


* 
**Real-time**: **Pusher** + Laravel Echo for instant, low-latency dispatch updates.


* 
**Database**: SQLite.



---

## 📱 On-Phone Experience

* 
**Victim Activation**: A one-tap high-visibility button triggers the "API Trust Stack" (KYC, SIM Swap, and Location).


* 
**Offline Fallback**: Automatic detection of data drops initiates SMS alerts for responders.


* 
**Real-time Mission Control**: Responders receive instant audible alerts via **Pusher** for nearby emergencies.



---

## 🔐 Responder Access

To ensure only trained personnel access the **Mission Control** dashboard, responders must authenticate using the following master key:

> **Access Key**: `NET-2026`

---

## ⚙️ Installation & Setup

### 1. PC / Development Setup

```bash
# Clone the repository
git clone https://github.com/your-username/netguard-emergency.git

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan migrate

# Start the local server
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. On-Phone / Mobile Setup (NativePHP)

To run NetGuard as a native application on a mobile device, follow this specific sequence:

1. 
**Compilation**: Run `npm run build` to compile all UI assets.


2. **Device Prep**: Enable **Developer Mode** and **USB Debugging** in your phone's system settings.
3. **Run Application**:
```bash
php artisan native:run

```


4. **Production Build**: To generate a final mobile installer:
```bash
php artisan native:build

```



---

## 👥 Team: Akanyijuka Darius

* 
**Akanyijuka Darius**: Lead Developer.


* 
**Akandwanaho Alvin**: Team Member.


* 
**Location**: Kampala, Uganda.



---

*This project is submitted for the Nokia Open Gateway Hackathon 2026.*
