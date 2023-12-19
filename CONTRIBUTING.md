# Contribution Guidelines (Last modified on 18th December 2023)

First of all, thank you for wanting to provide a bugfix or a new feature for the mailcow community, it's because of your help that the project can continue to grow!

## Pull Requests (Last modified on 18th December 2023)

However, please note the following regarding pull requests:

1. **ALWAYS** create your PR using the staging branch of your locally cloned mailcow instance, as the pull request will end up in said staging branch of mailcow once approved. Ideally, you should simply create a new branch for your pull request that is named after the type of your PR (e.g. `feat/` for function updates or `fix/` for bug fixes) and the actual content (e.g. `sogo-6.0.0` for an update from SOGo to version 6 or `html-escape` for a fix that includes escaping HTML in mailcow).
2. Please **keep** this pull request branch **clean** and free of commits that have nothing to do with the changes you have made (e.g. commits from other users from other branches). *If you make changes to the `update.sh` script or other scripts that trigger a commit, there is usually a developer mode for clean working in this case.
3. **Test your changes before you commit them as a pull request.** <ins>If possible</ins>, write a small **test log** or demonstrate the functionality with a **screenshot or GIF**. *We will of course also test your pull request ourselves, but proof from you will save us the question of whether you have tested your own changes yourself.*
4. Please **ALWAYS** create the actual pull request against the staging branch and **NEVER** directly against the master branch. *If you forget to do this, our moobot will remind you to switch the branch to staging.*
5. Wait for a merge commit: It may happen that we do not accept your pull request immediately or sometimes not at all for various reasons. Please do not be disappointed if this is the case. We always endeavor to incorporate any meaningful changes from the community into the mailcow project.
6. If you are planning larger and therefore more complex pull requests, it would be advisable to first announce this in a separate issue and then start implementing it after the idea has been accepted in order to avoid unnecessary frustration and effort!

---

## Issue Reporting (Last modified on 18th December 2023)

If you plan to report a issue within mailcow please read and understand the following rules:

1. **ONLY** use the issue tracker for bug reports or improvement requests and NOT for support questions. For support questions you can either contact the [mailcow community on Telegram](https://docs.mailcow.email/#community-support-and-chat) or the mailcow team directly in exchange for a [support fee](https://docs.mailcow.email/#commercial-support).
2. **ONLY** report an error if you have the **necessary know-how (at least the basics)** for the administration of an e-mail server and the usage of Docker. mailcow is a complex and fully-fledged e-mail server including groupware components on a Docker basement and it requires a bit of technical know-how for debugging and operating.
3. **ONLY** report bugs that are contained in the latest mailcow release series. *The definition of the latest release series includes the last major patch (e.g. 2023-12) and all minor patches (revisions) below it (e.g. 2023-12a, b, c etc.).* New issue reports published starting from January 1, 2024 must meet this criterion, as versions below the latest releases are no longer supported by us.
4. When reporting a problem, please be as detailed as possible and include even the smallest changes to your mailcow installation. Simply fill out the corresponding bug report form in detail and accurately to minimize possible questions.
5. **Before you open an issue/feature request**, please first check whether a similar request already exists in the mailcow tracker on GitHub. If so, please include yourself in this request.
6. When you create a issue/feature request: Please note that the creation does <ins>**not guarantee an instant implementation or fix by the mailcow team or the community**</ins>.
7. Please **ALWAYS** anonymize any sensitive information in your bug report or feature request before submitting it.

### Quick guide to reporting problems:
1. Read your logs; follow them to see what the reason for your problem is.
2. Follow the leads given to you in your logfiles and start investigating.
3. Restarting the troubled service or the whole stack to see if the problem persists.
4. Read the [documentation](https://docs.mailcow.email/) of the troubled service and search its bugtracker for your problem.
5. Search our [issues](https://github.com/mailcow/mailcow-dockerized/issues) for your problem.
6. [Create an issue](https://github.com/mailcow/mailcow-dockerized/issues/new/choose) over at our GitHub repository if you think your problem might be a bug or a missing feature you badly need. But please make sure, that you include **all the logs** and a full description to your problem.
7. Ask your questions in our community-driven [support channels](https://docs.mailcow.email/#community-support-and-chat).

## When creating an issue/feature request or a pull request, you will be asked to confirm these guidelines.