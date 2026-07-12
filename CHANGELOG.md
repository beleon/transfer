# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
follows [Semantic Versioning](https://semver.org/).

## [1.0.0]

First stable release. The core functionality is stable and the app is now in
maintenance mode. Going forward the focus is bug fixes, keeping up with new
Nextcloud releases, and occasionally adding a feature. The 0.7.x line was the
initial re-release after the project changed hands, covering a full frontend
rewrite, a build-system migration, and a dependency cleanup. This release adds
live progress, immediate transfers, and a settings page, which rounds out the
feature set for 1.0.

### Added
- Personal settings page (Settings > Transfer) showing active transfers with
  live progress bars, filenames, URLs, and cancel buttons. It polls every 3
  seconds and pauses while the tab is hidden.
- Immediate transfer option. A checkbox in the dialog starts a transfer right
  away instead of waiting for the next cron cycle. Closing the tab stops the
  heartbeat, which cancels the server-side transfer within 10 seconds.
- Progress tracking and cancellation via Nextcloud's distributed cache. Each
  transfer reports progress through `CURLOPT_PROGRESSFUNCTION`, and a cancel
  flag aborts the download. This requires Redis or Memcached. The background
  queue still works without it.

### Changed
- Nextcloud 34 is now supported. The compatibility range is 29 to 34.
- Hardened `TransferService`: per-transfer IDs, stall detection that aborts a
  transfer after 120 seconds without data, consolidated exception handling
  that always cleans up the temp file, and a retry loop for concurrent writes of
  the same filename.

### Fixed
- Checksums pasted in uppercase no longer fail verification. The comparison is
  case-insensitive and constant-time now.
- Toasts no longer rely on the deprecated OC.Notification API, which newer
  Nextcloud versions have removed. A failed immediate transfer used to leave
  the dialog stuck on "Starting" because of this.
- Cancelling an immediate transfer, from the dialog or from the settings page,
  returns the dialog to the form with all fields kept instead of closing it.
- A failing download no longer produces a 500 on the Nextcloud server. The
  remote site returning an error is a normal outcome: the start endpoint now
  reports what happened (remote status code, unreachable host, blocked URL,
  checksum mismatch) and the dialog shows a matching message.

### Dependencies
- dompurify 3.4.2 to 3.4.11
- qs 6.15.1 to 6.15.2
- vite 7.3.3 to 7.3.5

### Translations
- Updated translations from Transifex.
