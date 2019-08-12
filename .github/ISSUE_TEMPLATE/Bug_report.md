---
name: Bug report
about: Report a bug for this project

---
<!--
  For community support and other discussions, you are welcome to visit us on our community channels listed at https://mailcow.github.io/mailcow-dockerized-docs/#community-support. For professional commercial support, please check out https://mailcow.github.io/mailcow-dockerized-docs/#commercial-support instead
-->

**Prior to placing the issue, please check following:** *(fill out each checkbox with a `X` once done)*
- [ ] I understand that ignoring below instructions might result in immediate closing and deletion of my issue.
- [ ] I do promise I will carefully read below instructions to help getting my issue fixed quicker.
- [ ] I have understood that answers are voluntary and community-driven, and not commercial support.
- [ ] I have verified that my issue has not been already answered in the past. I also checked previous [issues](https://github.com/mailcow/mailcow-dockerized/issues).

---

**Description of the bug**: What kind of issue have you *exactly* come across?
<!--
  This should be a clear and concise description of what the bug is. What EXACTLY does happen?
  If applicable, add screenshots to help explain your problem. Very useful for bugs in mailcow UI.
  Write your detailed description below.
-->

My issue is...

**Reproduction of said bug**: How *exactly* do you reproduce the bug?
<!--
  Here it is really helpful to know how exactly you are able to reproduce the reported issue.
  Meaning: What are the exact steps - one by one - to get the above described behavior.
  Screenshots can be added, if helpful. Add the text below.
-->

1. I go to...
2. And then to...
3. But once I do...

**Expected behavior**: What did you expect to happen instead?
<!--
  We now know what kind of issue you are experiencing and how, and the best case, this can be
  reproduced in a reliable way. Please tell us now, what you expected to happen.
  This may be just a few sentences. Please add text below.
-->

I thought I can ... but then this happened.

**Undertaken actions**: What actions have you tried to solve the issue?
<!--
  Near the end of the issue we would like to know what you have tried fixing the reported issue.
  This helps to prevent all kind of "Have you already tried this and that?" questions which might
  delay the actual solution in the first place. Please be accurate. Add actions below.
-->

1. I have tried restarting...
2. I checked...
3. I did...

__I have tried or I do...__ *(fill out each checkbox with a `X` if applicable)*
- [ ] In case of WebUI issue, I have tried clearing the browser cache and the issue persists.
- [ ] I do run mailcow on a Synology, QNAP or any other sort of NAS. (Be honest!)

**System informationg**
<!--
  In this stage we would kindly ask you to attach logs or general system information about your setup.
  Please carefully read the questions and instructions below.
-->

Further information (where applicable):

| Question | Answer |
| --- | --- |
| My operating system | I_DO_REPLY_HERE |
| Is Apparmor, SELinux or similar active? | I_DO_REPLY_HERE |
| Virtualization technlogy (KVM, VMware, Xen, etc) | I_DO_REPLY_HERE |
| Server/VM specifications (Memory, CPU Cores) | I_DO_REPLY_HERE |
| Docker Version (`docker version`) | I_DO_REPLY_HERE |
| Docker-Compose Version (`docker-compose version`) | I_DO_REPLY_HERE |
| Reverse proxy (custom solution) | I_DO_REPLY_HERE |

Further notes:
 - Output of `git diff origin/master`, any other changes to the code? If so, please post them.
 - All third-party firewalls and custom iptables rules are unsupported. Please check the Docker docs about how to use Docker with your own ruleset. Nevertheless, iptabels output can help _us_ to help _you_: `iptables -L -vn`, `ip6tables -L -vn`, `iptables -L -vn -t nat` and `ip6tables -L -vn -t nat `
 - Check `docker exec -it $(docker ps -qf name=acme-mailcow) dig +short stackoverflow.com @172.22.1.254` (set the IP accordingly, if you changed the internal mailcow network) and `docker exec -it $(docker ps -qf name=acme-mailcow) dig +short stackoverflow.com @1.1.1.1` - output? Timeout?
 
 General logs:
- Please take a look at the [official documentation](https://mailcow.github.io/mailcow-dockerized-docs/debug-logs/).
