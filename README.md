# Story chain (mod_storychain)

A Moodle activity for writing stories together, one sentence each, in the style of the "exquisite
corpse" game. Each writer sees only the sentence just before theirs. When a story is finished, the
teacher reveals it, usually on a projector, and the class reads what it made together.

It is designed mainly for live, in-classroom use.

**Status: alpha (0.1.0).**

## Requirements

- Moodle 5.2 or 5.3.
- Recommended: [tool_realtime](https://moodle.org/plugins/tool_realtime) by Marina Glancy, for instant
  turn handover and synced screens. Without it, open pages poll the server every few seconds (site
  setting) and everything still works. Teachers see a notice when it is missing.

## How it works

- **Modes.** *Single chain*: one story per group (or per class without groups). *Parallel chains*:
  2-10 stories per group at once, and students rotate between them so fewer sit waiting.
- **Turns are handed out, not grabbed.** A free story goes to the online student with the fewest turns
  so far. Ties go first to whoever did not write that story's last sentence, then to whoever has written
  least in it, then to whoever has waited longest. "Online" means the activity is open in a browser,
  reported by the page's heartbeat. A turn is a time-limited claim (30 s to 10 min, a teacher setting).
  If it runs out, the student passes, or the student closes the page, the turn moves on. Passed or
  expired turns count as turns, so nobody gets every turn back at once.
- **The writer** sees the previous sentence (or the teacher's opening sentence, or a blank page), the
  story's title if the teacher allows it, a countdown and a box for one sentence (length limit set by
  the teacher).
- **Context length** (teacher setting, 1-10, default 1): how many of the story's latest sentences the
  next writer can read. 1 is the classic game. More is like giving an AI a longer context window: the
  writer remembers further back, but the start of the story still stays hidden until the reveal.
- **Waiting students** see a "someone is writing" indicator for stories in progress and, if the
  teacher allows it, how many sentences each story has. They never see the sentences.
- **A story is finished** after a fixed number of sentences, at a deadline, or once every student in
  the group has written in it. The teacher can also close a story at any time, and reopen it.
- **Teacher's live view**: every story in full while it is written; close, reveal, reopen; hide or show
  any sentence.
- **Presenting**: step through a story one sentence at a time on the projector (buttons or arrow keys),
  or show it all at once. Students who have the activity open follow the teacher's place on their own
  screens. Presenting a story also reveals it, so students can read it afterwards.
- **Anonymous**: no author names or ids are ever sent to a browser, the teacher's included.
- **Groups**: Moodle's standard group mode. With separate or visible groups, each group writes its own
  stories.

## Capabilities

- `mod/storychain:view`: open the activity and read revealed stories.
- `mod/storychain:write` (students): take turns.
- `mod/storychain:manage` (teachers): live view, close, reveal, present, hide sentences.

## Privacy, backup and reset

The privacy provider exports and deletes sentences, turn counts and presence. Backup includes the
stories when user data is included. Course reset can delete all stories and shifts deadlines.

## Licence

GNU GPL v3 or later. Copyright 2026 Adam Jenkins <adam@wisecat.net>.
