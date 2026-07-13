# Design Document

## Overview

This feature implements automatic opening of the Veda AI chat interface when students submit assignments in Moodle. The design follows Moodle's event observer pattern to capture assignment submission events server-side and uses session-based signaling to trigger client-side iframe opening on the subsequent page load.

## Architecture

### High-Level Flow

```
Student Submits Assignment
         ↓
Assignment Module triggers event
         ↓
Veda Event Observer captures event
         ↓
Observer sets session flag (veda_auto_open)
         ↓
Page redirects/reloads after submission
         ↓
Veda Plugin (lib.php) detects session flag
         ↓
Plugin injects auto-open JavaScript
         ↓
JavaScript opens Veda iframe on DOM ready
         ↓
Session flag is cleared
```

### Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Moodle Core                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │         Assignment Module (mod_assign)             │     │
│  │  - Handles submission logic                        │     │
│  │  - Triggers submission events                      │     │
│  └────────────────────────────────────────────────────┘     │
│                          ↓                                   │
│  ┌────────────────────────────────────────────────────┐     │
│  │         Event System (core/event)                  │     │
│  │  - Routes events to registered observers           │     │
│  └────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                  Veda Plugin (local/veda)                    │
│  ┌────────────────────────────────────────────────────┐     │
│  │  Event Observer (classes/event/observer.php)       │     │
│  │  - Listens for submission events                   │     │
│  │  - Validates frontend type                         │     │
│  │  - Sets session flag                               │     │
