# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Monolog handler for Apache Kafka that enables PHP applications to send log messages to Kafka topics using the rdkafka PHP extension.

## Common Development Commands

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run unit tests only
vendor/bin/phpunit tests --group=Unit

# Run integration tests (requires Kafka setup)
KAFKA_PATH=/path/to/kafka STEP_TIMEOUT=30 vendor/bin/phpunit tests --group=Integration

# Run specific test
vendor/bin/phpunit tests/Monolog/Handler/KafkaHandlerTest.php --filter=testHandler
```

## Architecture

The codebase consists of a single main component:

**KafkaHandler** (`src/Monolog/Handler/KafkaHandler.php`):
- Extends Monolog's `AbstractProcessingHandler`
- Accepts a pre-configured `RdKafka\Producer` instance in the constructor
- Implements proper resource cleanup via destructor with `flush()` to ensure messages are sent before shutdown (critical for rdkafka 4.0+)
- Uses automatic partition assignment (`RD_KAFKA_PARTITION_UA`)
- Provides configurable flush timeout via `setFlushTimeout()`

## Testing Strategy

Tests are split into two groups:
- **Unit tests** (`@group Unit`): Mock Kafka components, test handler logic
- **Integration tests** (`@group Integration`): Spin up actual Kafka/Zookeeper instances

Integration tests use helper scripts (`travis-install-kafka.sh`, `travis-install-librdkafka.sh`) to set up the test environment.

## Version Compatibility

- PHP 8.1+ (required by Monolog 3.x)
- rdkafka 4.x/5.x
- Monolog ^3.0

## Key Implementation Notes

1. The handler requires a configured `RdKafka\Producer` instance - it doesn't create one internally
2. Default log format: `[%datetime%] %channel%.%level_name%: %message% %context% %extra%`
3. The flush timeout is crucial for ensuring messages are sent before PHP process termination
4. Topic configuration can be customized via optional `TopicConf` parameter