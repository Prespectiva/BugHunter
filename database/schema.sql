CREATE TABLE IF NOT EXISTS users (
    user_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name     TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL
                  CHECK (role IN ('customer', 'tester', 'developer', 'admin')),
    created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    code       TEXT    NOT NULL UNIQUE
               CHECK (length(code) BETWEEN 2 AND 10),
    name       TEXT    NOT NULL,
    lead_id    INTEGER REFERENCES users (user_id) ON DELETE SET NULL,
    created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bugs (
    bug_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id  INTEGER NOT NULL REFERENCES projects (project_id) ON DELETE CASCADE,
    reporter_id INTEGER NOT NULL REFERENCES users (user_id) ON DELETE RESTRICT,
    assignee_id INTEGER REFERENCES users (user_id) ON DELETE SET NULL,
    title       TEXT    NOT NULL CHECK (length(trim(title)) > 0),
    steps       TEXT,
    severity    TEXT    NOT NULL
                CHECK (severity IN ('Critical', 'Major', 'Minor', 'Trivial')),
    priority    TEXT    NOT NULL DEFAULT 'Medium'
                CHECK (priority IN ('High', 'Medium', 'Low')),
    status      TEXT    NOT NULL DEFAULT 'New'
                CHECK (status IN ('New', 'Assigned', 'In Progress', 'Fixed', 'Verified', 'Closed', 'Reopened')),
    created_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bugs_project ON bugs (project_id);
CREATE INDEX IF NOT EXISTS idx_bugs_status  ON bugs (status);