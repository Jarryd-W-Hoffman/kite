# Changelog

What's new in each version of Kite. The Welcome tab shows the entry for the version you're running, and each GitHub release uses the same entry as its notes.

Write entries for people using Kite, not for developers. When releasing, rename **Unreleased** to the new version and date, e.g. `## [0.2.0] - 2026-10-20`. The release workflow won't publish a version without an entry.

## [Unreleased]

### Added

- **Resizable sidebars.** Drag the edge of the sidebar or the note details panel to widen it. Double-click the edge to put it back. With the edge focused, the arrow keys resize it too. Kite remembers the widths.

### Fixed

- **Renaming or moving a big folder is much quicker.** In a large vault, Kite could stop responding for a minute or more while it moved a folder of a few hundred notes. It now takes a few seconds.

## [0.3.2] - 2026-10-24

### Fixed

- **Locked notes stay locked when you go back.** After locking the vault, pressing back to a locked note you'd opened could show its text for a moment before it closed again. Kite now forgets those pages when the vault locks.
- **Links in locked notes follow renames.** Renaming or moving a note now updates the links to it inside locked notes too. If the vault is locked at the time, they're updated the next time you unlock it.

### Security

- **Only Kite's own code runs in its window.** Kite now tells its window to refuse any script it didn't ship, as a second safeguard for locked notes: even if a note ever slipped something past Kite's checks, it couldn't run.

## [0.3.1] - 2026-10-23

### Fixed

- **Opening an attachment no longer stops notes from opening** (Linux). After an image or PDF opened in its own app from the file tree, Kite stopped responding to clicks until it was restarted. **Open logs** in Preferences → Diagnostics did the same.

## [0.3.0] - 2026-10-23

### Added

- **Flowchart shapes on canvases.** Choose **Add a shape** for a process, start or end, decision, input or output, connector, predefined process, document or database, or change a card's shape with **Shape**. Shapes match Obsidian's Advanced Canvas plugin.
- **Arrows your way.** Select a connection on a canvas to put arrowheads at one end, both ends or neither, reverse its direction, or make it dashed or dotted.
- **Optional crash reports.** If you turn them on (Preferences → Privacy, or when the Welcome tab asks), Kite sends a report when something goes wrong so it can be fixed. Reports never include your notes, and folder paths are removed. They're off unless you choose otherwise.
- **Welcome in the tray menu:** open the Welcome tab from Kite's tray icon (the menu bar on macOS).
- **Merge changes.** When a note changes on disk while you're editing it, choose **Merge…** to combine the two: changes made on only one side are kept, and where both changed the same lines you pick yours, theirs or both.
- **Conflicting copies from sync apps.** When Dropbox, Nextcloud, Obsidian Sync or Syncthing leaves a conflicting copy of a note, the note says so and offers to merge the copy back in and delete it.
- **Recently deleted.** A deleted note keeps its history for 30 days, whether you deleted it in Kite or elsewhere. Choose **Undo** right after deleting, or restore it later from **Recently deleted** in the vault menu, with its history and star. A deleted file that comes back gets its history back too.
- **Links to blocks.** End a paragraph or list item with `^an-id`, or choose **Copy link to this block** in the command palette, and link to it with `[[Note#^an-id]]` or embed just that block with `![[Note#^an-id]]`. Backlinks show which heading or block they point at, and the outline shows how many links each heading has.
- **Your own keyboard shortcuts.** Press Ctrl+/ (⌘/ on macOS) and choose **Customise…** to give a command like Search, New note or Graph view another shortcut, or none. Kite says when a shortcut is already taken, and **Reset all** puts them back.
- **Link previews.** Rest the pointer on a link in a note's preview to read the note it goes to, or just the heading or block it points at, without leaving where you are. In the editor, hold Ctrl (⌘ on macOS) over a link.

### Changed

- **Opening a big vault for the first time is faster:** Kite reads a vault of 10,000 notes about a fifth sooner.

### Fixed

- **Notes renamed or moved outside Kite** (in your file manager, Git or a sync app) keep their version history, star and comments, instead of starting again as new notes.
- **A note changed outside Kite while you were editing it** (in another app, a sync or the other pane) is no longer overwritten. Kite stops saving and lets you compare the two versions, then keep yours or use the one on disk.
- **Preferences:** choosing a section on the left no longer scrolls the whole window up and hides its title, and the section you chose (or scrolled to) is the one highlighted.
- **Times in your local time zone.** Quick capture stamped entries in UTC (09:44 for a thought captured at 19:44 in Brisbane), and today's daily note and template dates could be a day out near midnight. They now use your computer's time zone.
- **A tab for a deleted note** no longer opens an error page. Tabs for notes that are gone close themselves, tab titles follow renames, and an old link to a missing note goes back to the vault and says why.
- **Folders of attachments** no longer say "Empty" in the file tree. Images, PDFs and other files are listed under their folders, and choosing one opens it in its usual app.
- **The Vault passphrase menu item** is spaced like the others, with a normal-sized icon.
- **The Welcome tab's quick capture tip** shows the shortcut you set in Preferences, and is left out when quick capture is off.
- **Graph filters** explain themselves: rest the pointer on **Unresolved** or **Orphans** to see what they show.


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
