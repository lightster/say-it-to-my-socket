<?php

namespace Lstr\SayItToMySocket;

use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class FizzbuzzServer implements MessageComponentInterface
{
    /**
     * @var SplObjectStorage
     */
    private $clients;

    /**
     * @var int
     */
    private $next_client_id = 1;

    /**
     * @var int
     */
    private $iteration = 1;

    /**
     * @var array
     */
    private $messages_by_client_id = [];

    /**
     * @var ConnectionInterface[]
     */
    private $clients_without_messages = [];

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }

    /**
     * @param  ConnectionInterface $connection
     * @throws Exception
     */
    public function onOpen(ConnectionInterface $connection)
    {
        $this->addClient($connection);
    }

    /**
     * @param  ConnectionInterface $connection
     * @throws Exception
     */
    public function onClose(ConnectionInterface $connection)
    {
        $this->removeClient($connection);
    }

    /**
     * @param ConnectionInterface $connection
     * @param  Exception $e
     */
    public function onError(ConnectionInterface $connection, Exception $e)
    {
        var_dump($e->getMessage());
        $connection->close();
    }

    /**
     * Triggered when a client sends data through the socket
     *
     * @param  ConnectionInterface $from
     * @param  string $raw_message
     * @throws Exception
     */
    public function onMessage(ConnectionInterface $from, $raw_message)
    {
        $message = json_decode($raw_message, true);
        if (null === $message) {
            return;
        }

        if ('iterationMessage' === $message['messageType']) {
            $this->setMessageByClient($from, $message);
            return;
        }
    }

    /**
     * @param ConnectionInterface $connection
     */
    private function addClient(ConnectionInterface $connection)
    {
        $client_id = $this->next_client_id;

        $this->clients->attach($connection, $client_id);

        $this->clients_without_messages[$client_id] = $connection;

        ++$this->next_client_id;

        $this->notifyRemainingClients();
    }

    /**
     * @param ConnectionInterface $conn
     */
    private function removeClient(ConnectionInterface $conn)
    {
        $client_id = $this->getClientIdForClient($conn);
        $this->clients->detach($conn);

        $this->deleteMessageByClientId($client_id);
    }

    /**
     * @param int $client_id
     */
    private function deleteMessageByClientId($client_id)
    {
        unset($this->messages_by_client_id[$client_id]);
        unset($this->clients_without_messages[$client_id]);
    }

    /**
     * @param ConnectionInterface $client
     * @param array $message
     */
    private function setMessageByClient(ConnectionInterface $client, array $message)
    {
        $client_id = $this->getClientIdForClient($client);
        if (!isset($this->clients_without_messages[$client_id])) {
            return;
        }

        if ("{$message['iteration']}" !== "{$this->iteration}") {
            $this->requestCurrentIterationFromClient($client);
            return;
        }

        $this->messages_by_client_id[$client_id] = $message;
        unset($this->clients_without_messages[$client_id]);

        if ($this->clients_without_messages) {
            return;
        }

        $this->processIteration();
    }

    private function processIteration()
    {
        $iteration = $this->iteration;
        ++$this->iteration;

        $messages = [];
        foreach ($this->messages_by_client_id as $message) {
            $messages[$message['messagePriority']] = $message['message'];
        }
        ksort($messages);
        echo implode('', $messages) . "\n";

        if ($iteration >= 100) {
            exit(0);
        }

        $this->messages_by_client_id = [];
        foreach ($this->clients as $client) {
            $client_id = $this->getClientIdForClient($client);
            $this->clients_without_messages[$client_id] = $client;
        }
        usleep(500000);
        $this->notifyRemainingClients();
    }

    private function notifyRemainingClients()
    {
        foreach ($this->clients_without_messages as $client_id => $connection) {
            $this->requestCurrentIterationFromClient($connection);
        }
    }

    /**
     * @param ConnectionInterface $connection
     */
    private function requestCurrentIterationFromClient(ConnectionInterface $connection)
    {
        $connection->send(json_encode([
            'messageType' => 'messageRequest',
            'iteration'   => $this->iteration,
        ]));
    }

    /**
     * @param ConnectionInterface $conn
     * @return mixed|object
     */
    private function getClientIdForClient(ConnectionInterface $conn)
    {
        $client_id = $this->clients[$conn];

        return $client_id;
    }
}
