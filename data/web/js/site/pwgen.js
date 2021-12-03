function randPassword(letters, numbers, special, lettersAndNumbers) {
  var chars = [
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz", // letters
    "0123456789", // numbers
    "!§$%&/()=?*-_:><^", // special characters
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789", // letters and numbers
  ]

  return [letters, numbers, special, lettersAndNumbers].map(function(len, i) {
    return Array(len).fill(chars[i]).map(function(x) {
      return x[Math.floor(Math.random() * x.length)]
    }).join('')
  }).concat().join('').split('').sort(function() {
    return 0.5 - Math.random()
  }).join('')
}

var PWGEN = {
  randomPassword: function(pwl) {
    // Return password with at least 8 characters (2 numbers and 2 special characters)
    return randPassword(4, 2, 2, (pwl && pwl > 8 ? pwl - 8 : 0))
  },
}
