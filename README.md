# Moodle Site with Veda AI Integration

This repository contains a custom Moodle 5.x installation integrated with the **Veda AI Ecosystem**. It includes several custom plugins for AI chat, automated proctoring, coding playgrounds, and custom dashboards.

## 🚀 Quick Setup

### 1. Clone & Initialize Submodules
Since several plugins are maintained in separate repositories, you must initialize them after cloning:
```bash
git clone [YOUR_REPO_URL]
git submodule update --init --recursive
```

### 2. Configure Moodle
Copy the distribution config and fill in your database and Veda credentials:
```bash
cp config-dist.php config.php
# Update config.php with your DB credentials, dataroot, and veda_fastapi_url
```

### 3. Install Dependencies
You must install the JavaScript dependencies for the core and the interactive plugins:

#### Root (Core Tools)
```bash
npm install
```

#### Course Dashboard (Interactive Frontend)
```bash
cd mod/coursedashboard/client
npm install
npm run build
```

#### AI Chat Block
```bash
cd blocks/ai_chat
npm install
```

## 🧩 Custom Plugins Overview

- **`local/veda`**: The core AI/Analytics bridge. Linked via Submodule.
- **`mod/coursedashboard`**: Custom role-based student/admin dashboard. Linked via Submodule.
- **`mod/codingplayground`**: Integrated IDE for programming activities.
- **`local/profile_enforcer`**: Mandates profile pictures and captures user data for AI sync.
- **`local/placement`**: Admin-only courses and placement dashboard.

## 🛡️ Repository Hygiene
- **config.php** is ignored. Never commit credentials.
- **node_modules/** and **dist/** are ignored. Always run `npm install` and `npm run build` after pulling updates.
- **Logs (*.log)** are ignored to keep the repo clean.
