$(document).ready(function() {
  var darkmode = localStorage.getItem("darkmode");
  localStorage.clear();
  localStorage.setItem("darkmode", darkmode);
});
