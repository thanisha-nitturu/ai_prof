# TinyMCE Video Lesson Plugin for Moodle™

The TinyMCE Video Lesson plugin adds an editor button that allows teachers to insert Video Lesson content directly into Moodle text editors.

It works in conjunction with the Video Lesson activity module (`mod_videolesson`) to simplify embedding video lesson content across the LMS.

---

## Purpose

This plugin enhances the Moodle TinyMCE editor by providing a dedicated button to insert Video Lesson content into:

- Course descriptions
- Labels
- Activity descriptions
- Page resources
- Any Moodle area that supports the TinyMCE editor

---

## Features

- TinyMCE editor integration
- One-click insertion of Video Lesson content
- Seamless integration with `mod_videolesson`
- Moodle-native implementation

---

## Requirements

* Moodle 4.1 or later
* TinyMCE editor enabled
* Video Lesson Activity (`mod_videolesson`) installed and configured
* Video Lesson Filter (`filter_videolesson`) installed and enabled if inserted Video Lesson content is rendered through Moodle text filtering

## Installation

1. Download the plugin ZIP file or clone this repository.

2. Rename the extracted folder to `videolesson` if needed.

3. Copy the folder to:

   ```text
   /path/to/moodle/lib/editor/tiny/plugins/videolesson
   ```

4. Log in to Moodle as an administrator.

5. Go to **Site administration → Notifications** and complete the installation.

6. Go to **Site administration → Plugins → Text editors → Manage editors**.

7. Ensure **TinyMCE editor** is enabled.

8. Ensure **Video Lesson Activity** (`mod_videolesson`) is installed and configured.

9. Ensure **Video Lesson Filter** (`filter_videolesson`) is enabled if inserted content should render inside Moodle text areas.

---

## Documentation

For installation, configuration, and usage guides, see:
https://www.mooplugins.com/docs-category/video-lesson-activity/

---

## Related plugins

Video Lesson TinyMCE Button is part of the Video Lesson plugin suite for Moodle.

| Plugin                                           | Purpose                                                                                                                     | GitHub                                                            | Moodle plugins directory                                                  |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Video Lesson Activity (`mod_videolesson`)        | Required. Provides the main Video Lesson activity, video progress tracking, completion rules, video library, and analytics. | [GitHub](https://github.com/mooplugins/moodle-mod_videolesson)    | [Moodle plugins directory](https://moodle.org/plugins/mod_videolesson)    |
| Video Lesson Filter (`filter_videolesson`)       | Recommended when inserted Video Lesson content needs to render inside Moodle text areas.                                    | [GitHub](https://github.com/mooplugins/moodle-filter_videolesson) | [Moodle plugins directory](https://moodle.org/plugins/filter_videolesson) |
| Video Lesson TinyMCE Button (`tiny_videolesson`) | This plugin. Adds a TinyMCE editor button for inserting Video Lesson content.                                               | [GitHub](https://github.com/mooplugins/moodle-tiny_videolesson)   | [Moodle plugins directory](https://moodle.org/plugins/tiny_videolesson)   |

### Recommended installation order

1. Install **Video Lesson Activity** (`mod_videolesson`) first.
2. Install **Video Lesson Filter** (`filter_videolesson`) if embedded content should render through Moodle text filtering.
3. Install **Video Lesson TinyMCE Button** (`tiny_videolesson`) to add the editor button.

---

## Release notes

### Version 1.0.1

Maintenance release with Moodle plugins directory review fixes and documentation updates.

Changes:

- Updated README documentation and related plugin links.
- Clarified that Video Lesson TinyMCE Button is a companion plugin for `mod_videolesson`.
- Clarified dependency on `filter_videolesson` for rendering inserted Video Lesson content.
- Clarified installation requirements and Moodle compatibility.
- Updated dependency information for Video Lesson Activity and Video Lesson Filter.
- Included fixes requested during the Moodle plugins directory review process.

### Version 1.0.0

Initial public release of TinyMCE Video Lesson Plugin for Moodle.

Included features:

- Added TinyMCE editor button for inserting Video Lesson content.
- Added integration with Video Lesson Activity (`mod_videolesson`).
- Added support for inserting Video Lesson content into course descriptions, labels, activity descriptions, Page resources, and other TinyMCE-supported text areas.
- Added Moodle-native TinyMCE plugin structure using the `tiny_videolesson` component.


---

## License

This plugin is licensed under the GNU GPL v3 or later.

See the LICENSE file for details.

---

Moodle™ is a registered trademark of Moodle Pty Ltd.  
This plugin is not affiliated with or endorsed by Moodle Pty Ltd.
