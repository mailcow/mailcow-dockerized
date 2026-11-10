$(document).ready(function() {
  var theme = localStorage.getItem("mailcow_theme");
  if (theme !== null) {
    localStorage.setItem("mailcow_theme", theme);
  }
});
