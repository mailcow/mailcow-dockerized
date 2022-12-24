$(document).ready(function() {
  var theme = localStorage.getItem("theme");
  localStorage.clear();
  localStorage.setItem("theme", theme);
});
