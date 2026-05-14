
# NetGuard Emergency

**Real-Time Network-Powered Emergency Response System for Uganda** 

NetGuard Emergency is a mobile and web-based alert system designed to slash emergency response times in regions with inconsistent connectivity. By leveraging **Nokia CAMARA (Open Gateway) APIs**, NetGuard transforms the mobile network into an active tool for pinpointing victims and ensuring critical data reaches responders without delay.

## 🏥 The Problem

In Uganda, emergency response is often critically slow, especially regarding road accidents involving boda-bodas and maternal health complications. Current challenges include:

* **Location Ambiguity**: GPS failures in rural areas or low-signal zones.

* **Network Congestion**: Vital alerts getting lost in standard traffic.

* **Connectivity Gaps**: Difficulty reaching victims when mobile data is unreliable.

## 🚀 The Solution

NetGuard enables bystanders or patients to trigger emergencies instantly via a one-tap app interface or USSD code. The system intelligently routes these alerts to the nearest Village Health Team (VHT), clinic, or trained responder.

### 📡 Nokia CAMARA API Integration (The Trust & Speed Stack)

NetGuard leverages the **Network as Code Developer Portal** to provide a coherent "API Story":


| API Name/Component | Functional Role | Business Impact |
| :--- | :--- | :--- |
| **Location Retrieval** | Pinpoints victim via network cells. | Reduces search time by 40% in GPS-dead zones. |
| **QoD (Quality on Demand)** | Prioritizes responder data traffic. | Ensures zero latency for Pusher alert signals. |
| **Reachability Status** | Detects data loss in real-time. | Triggers automatic SMS fallback for 100% uptime. |
| **SIM Swap/Security layer** | Checks for recent SIM changes. | Eliminates fraudulent "prank" emergency alerts. |
| **KYC(Know Your Customer)** | Verifies registered user identity. | Provides responders with verified patient names. |
| **AI Triage** | AI Logic. | Rule-based Symptom Classifier to recommend actions needed. |

---
## 🛠 Technology Stack

* **Frontend/Mobile**: Vue.js + Framework7 (Native-like UI) + Pinia.
* **Backend**: Laravel 12 (PHP) with Sanctum authentication.
* **Real-time**: **Pusher** + Laravel Echo for instant, low-latency dispatch updates.


* **Database**: SQLite.
---

## 📱 On-Phone Experience

* **Victim Activation**: A one-tap high-visibility button triggers the "API Trust Stack" (KYC, SIM Swap, and Location).

* **Offline Fallback**: Automatic detection of data drops initiates SMS alerts for responders.

* **Real-time Mission Control**: Responders receive instant audible alerts via **Pusher** for 
nearby emergencies.

* **For the Victim**: The app asks 2-3 rapid-fire questions. The AI Triage engine processes these instantly to tell the responder exactly what equipment to bring (e.g., "Heavy Bleeding - Bring Trauma Kit").

* **For the Responder**: The Mission Control dashboard displays an AI Priority Badge (Red/Yellow/Green) so they can manage multiple alerts efficiently.

---
## 🔐 Responder Access

To ensure only trained personnel access the **Mission Control** dashboard, responders must authenticate using the following master key:

> **Access Key**: `NET-2026`

---
## ⚙️ Installation & Setup

### 1. PC / Development Setup

```bash
# Clone the repository
git clone https://github.com/AkanyijukaDarius/NetGuard-Emergency.git

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan migrate

# Start the local server
```

### 2. On-Phone / Mobile Setup (NativePHP)

To run NetGuard as a native application on a mobile device, follow this specific sequence:

1. **Compilation**: Run `npm run build` to compile all UI assets.


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

* **Akanyijuka Darius**: Lead Developer.
* **Akandwanaho Alvin**: Team Member.
* **Location**: Kampala, Uganda.

---
*This project is submitted for the  Africa Ignite Hackathon  2026.* **GSMA Open Gateway**
