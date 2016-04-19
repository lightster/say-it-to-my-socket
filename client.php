<?php

require __DIR__ . '/vendor/autoload.php';


$forker = new Forker();

$forker->forkRows(function ($key, $callback) {
    $callback();
}, [
    'counter' => function () {
        runWebsocket(3, function ($iteration_number) {
            if ($iteration_number % 3 == 0 || $iteration_number % 5 == 0) {
                return '';
            }

            return $iteration_number;
        });
    },
    'fizzer' => function () {
        runWebsocket(4, function ($iteration_number) {
            if ($iteration_number % 3 != 0) {
                return '';
            }

            return 'fizz';
        });
    },
    'buzzer' => function () {
        runWebsocket(5, function ($iteration_number) {
            if ($iteration_number % 5 != 0) {
                return '';
            }

            return 'buzz';
        });
    },
]);

function runWebsocket($message_priority, $iteration_callback)
{
    \Ratchet\Client\connect('ws://127.0.0.1:8085')->then(
        function ($connection) use ($message_priority, $iteration_callback) {
            $connection->on(
                'message',
                function ($data) use ($connection, $message_priority, $iteration_callback) {
                    $message = json_decode($data, true);
                    $connection->send(json_encode([
                        'messageType'     => 'iterationMessage',
                        'iteration'       => $message['iteration'],
                        'message'         => $iteration_callback($message['iteration']),
                        'messagePriority' => $message_priority,
                    ]));
                }
            );
        },
        function ($e) {
            echo "Could not connect: {$e->getMessage()}\n";
        }
    );
}


class Forker
{
    public function fork(callable $fork_callback, $fork_count, $data)
    {
        $pids = [];

        for ($i = 1; $i <= $fork_count; $i++) {
            $pid = pcntl_fork();

            if (-1 == $pid) {
                throw new Exception("Could not create fork #{$i}.");
            }

            if (!$pid) {
                $exit_status = (int)call_user_func($fork_callback, $i, $data);
                exit($exit_status);
            }

            $pids[$pid] = $i;
        }

        $exit_statuses = [];
        while ($pids) {
            $fork_status = null;
            $pid = pcntl_wait($fork_status);

            if (-1 == $pid) {
                throw new Exception("Could not get the status of remaining forks.");
            }

            $exit_statuses[$pids[$pid]] = pcntl_wexitstatus($fork_status);

            unset($pids[$pid]);
        }

        return $exit_statuses;
    }

    public function forkRows(callable $fork_callback, $fork_rows)
    {
        $keys = array_keys($fork_rows);

        $exit_statuses = $this->fork(function ($i, $data) use ($fork_callback) {
            $key = $data['keys'][$i - 1];
            $row = $data['rows'][$key];

            return call_user_func($fork_callback, $key, $row);
        }, count($fork_rows), ['keys' => $keys, 'rows' => $fork_rows]);

        $row_exit_statuses = [];
        foreach ($exit_statuses as $i => $exit_status) {
            $row_exit_status[$keys[$i]] = $exit_status;
        }

        return $row_exit_statuses;
    }
}
