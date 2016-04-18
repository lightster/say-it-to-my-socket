<html>
    <body>
        <script type="text/javascript">
            var createFizzbuzzClient = function (messagePriority, iterationCallback) {
                var connection = new WebSocket('ws://v.com:8085');
                connection.onmessage = function (event) {
                    var message = JSON.parse(event.data);
                    connection.send(JSON.stringify({
                        messageType: 'iterationMessage',
                        iteration: message.iteration,
                        message: iterationCallback(message.iteration),
                        messagePriority: messagePriority
                    }));
                };
            };

            var counter = createFizzbuzzClient(0, function (iterationNumber) {
                if (iterationNumber % 3 == 0 || iterationNumber % 5 == 0) {
                    return '';
                }

                return iterationNumber;
            });
            var fizzer = createFizzbuzzClient(1, function (iterationNumber) {
                if (iterationNumber % 3 != 0) {
                    return '';
                }

                return 'fizz';
            });
            var buzzer = createFizzbuzzClient(2, function (iterationNumber) {
                if (iterationNumber % 5 != 0) {
                    return '';
                }

                return 'buzz';
            });
        </script>
    </body>
</html>
