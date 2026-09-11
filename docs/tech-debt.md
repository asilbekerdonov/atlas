# Technical debt

- Admin editing another profile is temporarily disabled in UI to prevent
  silent data corruption. Backend `Voter::EDIT` remains, autosave
  target-profile — TODO.
