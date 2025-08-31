<!DOCTYPE html>
<head>
  <title>Pusher Test</title>
  <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
  <script>

    // Enable pusher logging - don't include this in production
    Pusher.logToConsole = true;

    var pusher = new Pusher('5fc000b382a90f691def', {
      cluster: 'eu'
    });

    var channel = pusher.subscribe('App.Models.User.2'); // Replace 1 with actual user ID
    channel.bind('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', function(data) {
        console.log('Notification received:', data);
});
  </script>
</head>
<body>
  <h1>Pusher Test</h1>
  <p>
    Try publishing an event to channel <code>my-channel</code>
    with event name <code>my-event</code>.
  </p>
</body>