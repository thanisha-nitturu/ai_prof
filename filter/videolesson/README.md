# Video Lesson Filter for Moodle™

The Video Lesson Filter is a Moodle™ text filter that enables automatic rendering of Video Lesson content inside Moodle text areas.

It works in conjunction with the Video Lesson activity module (`mod_videolesson`) to display embedded video content in course sections, labels, and activity descriptions.

---

## Purpose

This plugin allows Video Lesson content to be rendered dynamically wherever supported within Moodle’s text filtering system.

Without this filter enabled, embedded Video Lesson references may not render correctly inside formatted text areas.

---

## Features

- Automatic rendering of Video Lesson embeds
- Compatible with Moodle’s text filtering system
- Works across:
  - Course section descriptions
  - Labels
  - Activity descriptions
  - Page resources
- Lightweight and Moodle-native implementation

---

## Requirements

* Moodle 4.1 or later
* Video Lesson Activity (`mod_videolesson`) installed and configured

---

## Installation

1. Download the plugin ZIP file or clone this repository.
2. Rename the extracted folder to `videolesson` if needed.
3. Copy the folder to:   /path/to/moodle/filter/videolesson
4. Log in to Moodle as an administrator.
5. Go to Site administration → Notifications and complete the installation.
6. Go to Site administration → Plugins → Filters → Manage filters.
7. Enable Video Lesson Filter.
8. Ensure Video Lesson Activity (mod_videolesson) is installed and configured.

---

## Documentation

For installation, configuration, and usage guides, see:
https://www.mooplugins.com/docs-category/video-lesson-activity/

---

## Related plugins

Video Lesson Filter is part of the Video Lesson plugin suite for Moodle.

| Plugin                                           | Purpose                                                                                                                     | GitHub                                                            | Moodle plugins directory                                                  |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Video Lesson Activity (`mod_videolesson`)        | Required. Provides the main Video Lesson activity, video progress tracking, completion rules, video library, and analytics. | [GitHub](https://github.com/mooplugins/moodle-mod_videolesson)    | [Moodle plugins directory](https://moodle.org/plugins/mod_videolesson)    |
| Video Lesson Filter (`filter_videolesson`)       | This plugin. Renders embedded Video Lesson content inside Moodle text areas.                                                | [GitHub](https://github.com/mooplugins/moodle-filter_videolesson) | [Moodle plugins directory](https://moodle.org/plugins/filter_videolesson) |
| Video Lesson TinyMCE Button (`tiny_videolesson`) | Optional. Adds a TinyMCE editor button so teachers can insert Video Lesson content more easily.                             | [GitHub](https://github.com/mooplugins/moodle-tiny_videolesson)   | [Moodle plugins directory](https://moodle.org/plugins/tiny_videolesson)   |

### Recommended installation order

1. Install **Video Lesson Activity** (`mod_videolesson`) first.
2. Install and enable **Video Lesson Filter** (`filter_videolesson`).
3. Optionally install **Video Lesson TinyMCE Button** (`tiny_videolesson`) for easier content insertion through the editor.

---

## Release notes

### Version 1.0.1

Maintenance release with Moodle plugins directory review fixes and documentation updates.

Changes:

- Updated README documentation and related plugin links.
- Clarified that Video Lesson Filter is a companion plugin for `mod_videolesson`.
- Clarified installation requirements and Moodle compatibility.
- Updated dependency information for Video Lesson Activity.
- Included fixes requested during the Moodle plugins directory review process.

### Version 1.0.0

Initial public release of Video Lesson Filter for Moodle.

Included features:

- Added Moodle text filter for Video Lesson embeds.
- Added automatic rendering of Video Lesson references in supported Moodle text areas.
- Added support for course section descriptions, labels, activity descriptions, and Page resources.
- Added integration with the Video Lesson activity module (`mod_videolesson`).

---

## License

This plugin is licensed under the GNU GPL v3 or later.

See the LICENSE file for details.

---

Moodle™ is a registered trademark of Moodle Pty Ltd.  
This plugin is not affiliated with or endorsed by Moodle Pty Ltd.
