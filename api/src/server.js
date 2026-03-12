import express from "express";

const app = express();
app.use(express.json());

const contactsByStudent = {
  "student-1": [
    { id: "c1", name: "Ali", email: "ali@alfabe.co" },
    { id: "c2", name: "Ayşe", email: "ayse@alfabe.co" },
    { id: "c3", name: "Teacher", email: "teacher@alfabe.co" }
  ]
};

const inboxByStudent = {
  "student-1": [
    { id: "m1", from: "Teacher", subject: "Bugünkü ödev", preview: "Matematik sayfa 12", unread: true, receivedAt: "2026-03-10T09:15:00Z" },
    { id: "m2", from: "Ali", subject: "Selam", preview: "Tenefüste görüşelim", unread: false, receivedAt: "2026-03-09T11:30:00Z" }
  ]
};

app.post("/auth/login", (req, res) => {
  const { username } = req.body;
  res.json({ token: "demo-token", role: "student", username });
});

app.get("/students/:id/contacts", (req, res) => {
  res.json(contactsByStudent[req.params.id] ?? []);
});

app.get("/students/:id/inbox", (req, res) => {
  res.json(inboxByStudent[req.params.id] ?? []);
});

app.post("/students/:id/send", (req, res) => {
  const { contactId, subject, text, attachmentSizeMB = 0 } = req.body;
  const contacts = contactsByStudent[req.params.id] ?? [];
  const allowed = contacts.find((c) => c.id === contactId);

  if (!allowed) {
    return res.status(403).json({ error: "Recipient is not in whitelist" });
  }

  if (attachmentSizeMB > 5) {
    return res.status(400).json({ error: "Attachment exceeds 5MB limit" });
  }

  // Placeholder: Send mail via SMTP transport here.
  return res.status(202).json({ status: "queued", to: allowed.email, subject, textLength: text?.length ?? 0 });
});

app.get("/parent/students/:id/summary", (req, res) => {
  const inbox = inboxByStudent[req.params.id] ?? [];
  res.json({
    studentId: req.params.id,
    sentCount: 3,
    receivedCount: inbox.length,
    safePreview: inbox.map((m) => ({ from: m.from, subject: m.subject, receivedAt: m.receivedAt }))
  });
});

const port = process.env.PORT || 8080;
app.listen(port, () => {
  console.log(`Alfabe API listening on :${port}`);
});
