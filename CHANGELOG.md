# Changelog

What's new in each version of Kite. The Welcome tab shows the entry for the version you're running, and each GitHub release uses the same entry as its notes.

Write entries for people using Kite, not for developers. When releasing, rename **Unreleased** to the new version and date, e.g. `## [0.2.0] - 2026-10-20`. The release workflow won't publish a version without an entry.

## [Unreleased]

### Added

- **Optional crash reports.** If you turn them on (Preferences → Privacy, or when the Welcome tab asks), Kite sends a report when something goes wrong so it can be fixed. Reports never include your notes, and folder paths are removed. They're off unless you choose otherwise.
- **Welcome in the tray menu:** open the Welcome tab from Kite's tray icon (the menu bar on macOS).
- **Recently deleted.** A deleted note keeps its history for 30 days, whether you deleted it in Kite or elsewhere. Choose **Undo** right after deleting, or restore it later from **Recently deleted** in the vault menu, with its history and star. A deleted file that comes back gets its history back too.

### Changed

- **Opening a big vault for the first time is faster:** Kite reads a vault of 10,000 notes about a fifth sooner.

### Fixed

- **Notes renamed or moved outside Kite** (in your file manager, Git or a sync app) keep their version history, star and comments, instead of starting again as new notes.
- **A note changed outside Kite while you were editing it** (in another app, a sync or the other pane) is no longer overwritten. Kite stops saving and lets you compare the two versions, then keep yours or use the one on disk.
- **Preferences:** choosing a section on the left no longer scrolls the whole window up and hides its title, and the section you chose (or scrolled to) is the one highlighted.

## [0.2.0] - 2026-10-17

### Added

- **A Welcome tab.** It opens beside your notes with quick actions, your recent notes and a few tips. After an update it shows what's new, like this. You can switch it off in **Preferences → Welcome**, or with the checkbox on the tab itself.
- **Update progress.** While a new version downloads, Kite shows how far along it is, so you know to keep it open.

### Fixed

- **"Could not install the update"** could flash up when you chose **Restart now**, even though the update installed fine.

## [0.1.1] - 2026-10-17

### Fixed

- **"Permission denied" error at every start.** Installed from the `.deb`, Kite tried to save its startup cache inside its own read-only install folder. It now saves it in your data folder, so the error is gone and Kite starts with its cache in place.

## [0.1.0] - 2026-10-17

The first release of Kite: a local-first desktop app for Markdown notes and the links between them. Your notes stay plain `.md` files in a folder you own.

- **Writing:** a fast editor with live preview, split and preview views, tabs, focus mode, slash commands, callouts, math, diagrams, tables, properties, templates and attachments.
- **Connecting:** `[[wiki links]]` that update when you rename or move a note, backlinks, unlinked mentions, tags and colour groups.
- **Finding:** full-text search with filters, saved searches, table and board views, and every task in one place.
- **Seeing:** a graph of every note and link, and canvases.
- **Learning:** spaced-repetition flashcards written in your notes.
- **Daily notes and quick capture** from anywhere.
- **Keeping safe:** encrypted locked notes, version history, comments, export, publishing and Notion import.
- **Updates:** Kite updates itself on Windows and Linux.
