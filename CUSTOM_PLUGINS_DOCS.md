# Moodle Custom Plugins Documentation

This document provides an overview of the custom Moodle plugins installed in this environment, including their purpose, folder structure, and build file integration.

---

## 1. Veda (`local/veda`)

**Purpose**: Veda is an AI-powered assistant and proctoring system. It provides real-time chat assistance and proctoring features (like face mesh detection) during assessments.

### Folder Structure
- `api/`: Contains the PHP API router and endpoints.
    - `index.php`: Main API entry point that routes requests to specific handlers.
    - `endpoints/`: Individual PHP scripts for various AI and proctoring actions (e.g., `text_message.php`, `assessment_violation.php`).
- `classes/`: Backend logic and helper services.
    - `api/`: Service classes for API interactions.
    - `helpers/`: Utility classes for logging and debugging.
- `static//build/`: Contains the compiled React frontend.
    - `assets/`: Compiled JavaScript and CSS bundles.
- `lang/`: Language strings for the plugin.
- `lib.php`: Main Moodle library file containing hooks and callbacks.
- `version.php`: Plugin version and dependency information.

### Build & Integration
- **Source**: The original React source is managed externally (often in a `client/` directory which might be excluded from the main repo).
- **Linking**: The frontend is integrated into Moodle using the `local_veda_before_footer()` hook in `lib.php`. This function dynamically scans the `static/build/assets/` folder for `.js` and `.css` files using `glob()` and injects them into every Moodle page.

---

## 2. Course Dashboard (`mod/coursedashboard`)

**Purpose**: Provides an enhanced, interactive dashboard for courses, offering student analytics and a modernized interface for both teachers and students.

### Folder Structure
- `api/`: PHP endpoints for fetching dashboard data.
- `db/`: Database installation and upgrade scripts.
- `dist/`: **Compiled Build Files**. Generated from the `client/` folder.
    - `assets/`: Contains `index.js` and `index.css`.
- `view.php`: The main entry page for the activity in Moodle.
- `lib.php`: Standard Moodle module functions.
- `lang/`: Plugin language translations.

### Build & Integration
- **Source**: Development happens in the `client/` directory. Running `npm run build` in `client/` populates the `dist/` folder.
- **Linking**: The `view.php` file initializes the frontend by:
    1. Passing configuration data (user info, course info) to `window.COURSEDASHBOARD` via a script tag.
    2. Explicitly loading the CSS and JS bundles from `dist/assets/` using `html_writer`. It includes a cache-busting parameter based on the file modification time.

---

## 3. Coding Playground (`mod/codingplayground`)

**Purpose**: An integrated development environment (IDE) that allows students to write, run, and test code directly within Moodle. It utilizes a Judge0/FastAPI backend for code execution.

### Folder Structure
- `db/`: Contains the database schema and uninstall logic.
- `dist/`: **Compiled Build Files** for the IDE frontend.
    - `assets/`: Compiled JavaScript and CSS.
- `lang/`: Language strings.
- `pix/`: Icons for the plugin.
- `proxy.php`: A PHP proxy that forwards API requests from the frontend to the external FastAPI backend (e.g., `http://localhost:8000`).
- `view.php`: The main page for the playground activity.
- `all_playgrounds.php`: A custom page to view all available playgrounds.
- `lib.php`: Moodle hooks for navigation and activity life-cycle.

### Build & Integration
- **Source**: The original source code for the playground IDE is maintained in a separate repository or directory (previously requested by the user to be moved to submodules).
- **Linking**: Similar to the Course Dashboard, `view.php` links the compiled files:
    1. It defines `window.CODINGPLAYGROUND` with API URLs and user contex        t.
    2. It injects `dist/assets/index.js` as a module and `dist/assets/index.css`.

---

## 4. Profile Enforcer (`local/profile_enforcer`)

**Purpose**: Ensures data integrity by forcing users to complete their profiles (specifically uploading a profile picture) before they can access other parts of Moodle.

### Folder Structure
- `amd/src/`: Contains original JavaScript modules (e.g., `trigger.js`) used for frontend interactions on the profile page.
- `classes/`:
    - `observer.php`: Contains event observers that trigger actions when Moodle events occur (e.g., `user_created`).
- `db/`:
    - `events.php`: Registers event observers with Moodle.
- `lib.php`: Contains the `local_profile_enforcer_after_require_login` hook.
- `version.php`: Plugin metadata.

### Integration
- **Linking**: This plugin primarily uses Moodle's internal hook system. The `after_require_login` callback in `lib.php` runs on every page load after authentication. If a user is missing a profile picture, it triggers a redirect to the profile edit page.
- **JavaScript**: Uses standard Moodle AMD (Asynchronous Module Definition) loading for its frontend logic.
