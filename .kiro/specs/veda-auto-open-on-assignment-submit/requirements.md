# Requirements Document

## Introduction

This feature enables automatic opening of the Veda AI chat interface when a student successfully submits an assignment in Moodle. The system will observe assignment submission events server-side and trigger the Veda iframe to open automatically, providing immediate AI assistance to students after submission.

## Glossary

- **Veda_Plugin**: The local Moodle plugin located at `/local/veda/` that integrates Veda AI chat functionality
- **Event_Observer**: A Moodle component that listens for and responds to system events following the observer pattern
- **Assignment_Submission_Event**: Moodle events triggered when a student submits an assignment (`\mod_assign\event\assessable_submitted` or `\mod_assign\event\submission_created`)
- **Veda_Iframe**: The Next.js iframe embedded in Moodle pages that displays the Veda AI chat interface
- **Session_Flag**: A server-side session variable that signals the frontend to automatically open the Veda iframe
- **LiveKit_Token**: Authentication token required to connect to the Veda AI backend service
- **Frontend_Type**: Configuration setting (`$CFG->veda_frontend_type`) that determines whether legacy React or Next.js frontend is used
- **Event_Definition_File**: The `/local/veda/db/events.php` file that registers event observers with Moodle
- **Observer_Class**: PHP class containing callback methods that handle specific events

## Requirements

### Requirement 1

**User Story:** As a student, I want the Veda AI chat to automatically open after I submit an assignment, so that I can immediately ask questions about the submission or receive feedback guidance.

#### Acceptance Criteria

1. WHEN a student successfully submits an assignment, THE Event_Observer SHALL capture the Assignment_Submission_Event
2. WHEN the Assignment_Submission_Event is captured, THE Event_Observer SHALL set a Session_Flag indicating auto-open is required
3. WHEN the page reloads after submission, THE Veda_Plugin SHALL detect the Session_Flag
4. WHEN the Session_Flag is detected, THE Veda_Plugin SHALL inject JavaScript to automatically open the Veda_Iframe
5. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL clear the Session_Flag to prevent repeated auto-opens

### Requirement 2

**User Story:** As a system administrator, I want the auto-open feature to work only with the Next.js frontend, so that the feature is compatible with the current Veda architecture.

#### Acceptance Criteria

1. WHERE Frontend_Type is set to 'nextjs', THE Veda_Plugin SHALL enable auto-open functionality
2. WHERE Frontend_Type is set to 'legacy', THE Veda_Plugin SHALL NOT enable auto-open functionality
3. WHEN Frontend_Type is not 'nextjs', THE Event_Observer SHALL still capture events but SHALL NOT set the Session_Flag

### Requirement 3

**User Story:** As a developer, I want the event observer to follow Moodle's standard event handling patterns, so that the implementation is maintainable and follows best practices.

#### Acceptance Criteria

1. THE Event_Observer SHALL be registered in the Event_Definition_File at `/local/veda/db/events.php`
2. THE Observer_Class SHALL be located at `/local/veda/classes/event/observer.php`
3. THE Observer_Class SHALL implement a static callback method that accepts the Assignment_Submission_Event as a parameter
4. WHEN the callback method is invoked, THE Observer_Class SHALL have access to full assignment and submission details from the event object
5. THE Event_Observer SHALL listen for both `\mod_assign\event\assessable_submitted` and `\mod_assign\event\submission_created` events

### Requirement 4

**User Story:** As a student, I want the auto-open to work seamlessly with the existing Veda interface, so that my experience is consistent and intuitive.

#### Acceptance Criteria

1. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL use the same iframe loading mechanism as manual opens
2. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL load the iframe with the user's email and name parameters
3. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL update the toggle button visual state to show the close icon
4. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL update the tooltip text to "Close VedaAI"
5. WHEN a student manually closes the auto-opened Veda_Iframe, THE Veda_Plugin SHALL follow the standard close procedure including sending the disconnect message

### Requirement 5

**User Story:** As a system administrator, I want the auto-open feature to apply globally to all assignments, so that students receive consistent AI assistance across all courses.

#### Acceptance Criteria

1. THE Event_Observer SHALL capture Assignment_Submission_Events from all courses in the Moodle instance
2. THE Event_Observer SHALL NOT filter events by course, assignment type, or user role
3. WHEN a submission occurs in any assignment, THE Event_Observer SHALL set the Session_Flag regardless of course context

### Requirement 6

**User Story:** As a developer, I want the auto-open mechanism to be reliable and not interfere with normal page operations, so that the feature enhances rather than disrupts the user experience.

#### Acceptance Criteria

1. WHEN the Session_Flag is set, THE Veda_Plugin SHALL only trigger auto-open on the immediate next page load
2. IF the Session_Flag is present but Frontend_Type is not 'nextjs', THEN THE Veda_Plugin SHALL clear the Session_Flag without opening the iframe
3. WHEN the auto-open JavaScript executes, THE Veda_Plugin SHALL wait for the DOM to be fully loaded before triggering the open action
4. IF the Veda_Iframe is already open when auto-open is triggered, THEN THE Veda_Plugin SHALL NOT reload or re-open the iframe
5. WHEN auto-open fails for any reason, THE Veda_Plugin SHALL log the error and clear the Session_Flag to prevent repeated failures

### Requirement 7

**User Story:** As a student, I want to be able to distinguish between manual and automatic opens, so that I understand why the chat interface appeared.

#### Acceptance Criteria

1. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL log a console message indicating auto-open was triggered
2. WHEN the Veda_Iframe is automatically opened, THE Veda_Plugin SHALL include metadata in the iframe URL or postMessage indicating the trigger was an assignment submission
3. THE Veda_Plugin SHALL maintain the same visual appearance for auto-opened and manually-opened iframes

### Requirement 8

**User Story:** As a system administrator, I want the event observer to handle errors gracefully, so that submission failures don't prevent students from submitting assignments.

#### Acceptance Criteria

1. IF the Event_Observer encounters an error while processing an event, THEN THE Event_Observer SHALL log the error without throwing an exception
2. IF setting the Session_Flag fails, THEN THE Event_Observer SHALL log the failure but SHALL NOT prevent the submission from completing
3. WHEN an error occurs in the Event_Observer, THE Event_Observer SHALL continue to process subsequent events normally
4. THE Event_Observer SHALL NOT modify or interfere with the assignment submission process itself
