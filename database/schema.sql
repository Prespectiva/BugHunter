CREATE TABLE IF NOT EXISTS users (
    user_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name     TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL CHECK (role IN ('customer', 'tester', 'developer', 'admin')),
    created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    project_id  INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT    NOT NULL UNIQUE CHECK (length(code) BETWEEN 2 AND 10),
    name        TEXT    NOT NULL,
    lead_id     INTEGER REFERENCES users (user_id) ON DELETE SET NULL,
    created_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    owner_id    INTEGER REFERENCES users (user_id),
    description TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS bugs (
    bug_id          INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id      INTEGER NOT NULL REFERENCES projects (project_id),
    author_id       INTEGER NOT NULL REFERENCES users (user_id),
    assignee_id     INTEGER REFERENCES users (user_id) ON DELETE SET NULL,
    title           TEXT    NOT NULL CHECK (length(trim(title)) > 0),
    description     TEXT    NOT NULL DEFAULT '',
    steps           TEXT    NOT NULL DEFAULT '',
    expected_result TEXT    NOT NULL DEFAULT '',
    actual_result   TEXT    NOT NULL DEFAULT '',
    priority        TEXT    NOT NULL DEFAULT 'Medium' CHECK (priority IN ('Critical', 'High', 'Medium', 'Low')),
    status          TEXT    NOT NULL DEFAULT 'New' CHECK (status IN ('New', 'Review', 'Confirmed', 'In Progress', 'Fixed', 'Closed', 'Rejected')),
    created_at      TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS comments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id     INTEGER NOT NULL REFERENCES bugs (bug_id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users (user_id),
    text       TEXT    NOT NULL,
    created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bug_history (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id     INTEGER NOT NULL REFERENCES bugs (bug_id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users (user_id),
    old_status TEXT    NOT NULL,
    new_status TEXT    NOT NULL,
    created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bugs_project  ON bugs (project_id);
CREATE INDEX IF NOT EXISTS idx_bugs_author   ON bugs (author_id);
CREATE INDEX IF NOT EXISTS idx_bugs_status   ON bugs (status);
CREATE INDEX IF NOT EXISTS idx_projects_owner ON projects (owner_id);
CREATE INDEX IF NOT EXISTS idx_comments_bug  ON comments (bug_id);
CREATE INDEX IF NOT EXISTS idx_history_bug   ON bug_history (bug_id);
CREATE INDEX IF NOT EXISTS idx_bugs_priority   ON bugs (priority);
CREATE INDEX IF NOT EXISTS idx_bugs_project_id ON bugs (project_id);
CREATE INDEX IF NOT EXISTS idx_bugs_assignee   ON bugs (assignee_id);
CREATE INDEX IF NOT EXISTS idx_bugs_created    ON bugs (created_at);
