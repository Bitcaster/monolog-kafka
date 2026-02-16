<?php

namespace Kozlice\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RdKafka\TopicConf;

/**
 * Apache Kafka handler (https://kafka.apache.org/)
 *
 * Usage example:
 *
 *    $config = new RdKafka\Conf();
 *    $config->set('metadata.broker.list', '127.0.0.1');
 *    $producer = new RdKafka\Producer($config);
 *    $logger = new Logger('my_logger');
 *    $logger->pushHandler(new KafkaHandler($producer, 'test'));
 *
 *    $logger->info('My logger is now ready');
 *
 * @author Valentin Nazarov <i.kozlice@gmail.com>
 */
class KafkaHandler extends AbstractProcessingHandler
{
    private ProducerTopic $topic;
    private Producer $producer;
    private int $flushTimeout = 1000;

    /**
     * @param Producer $producer Kafka message producer instance
     * @param string $topicName Kafka topic name (if it doesn't exist yet, will be created)
     * @param ?TopicConf $topicConfig Kafka topic config (optional)
     * @param int|string|Level $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Producer $producer, $topicName, ?TopicConf $topicConfig = null, $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->producer = $producer;
        if (!$topicConfig) {
            $topicConfig = new TopicConf();
        }
        $this->topic = $producer->newTopic($topicName, $topicConfig);
    }

    public function setFlushTimeout(int $flushTimeout): void
    {
        $this->flushTimeout = $flushTimeout;
    }

    public function __destruct()
    {
        // Non-blocking: trigger pending callbacks but never block during shutdown.
        // flush() ignores its timeout when the broker is unreachable (blocks on
        // request.timeout.ms per message internally), and with 10+ Producer
        // instances this causes 500s+ zombie workers. Losing buffered log
        // messages on shutdown is acceptable; hanging FPM workers is not.
        $this->producer->poll(0);
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param LogRecord $record
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $data = $record->formatted;
        $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $data);
        $this->producer->poll(0);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %context% %extra%');
    }
}
