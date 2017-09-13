<?php declare(strict_types=1);

namespace Dms\Web\Expressive\Language;

use Dms\Core\Exception\InvalidArgumentException;
use Dms\Core\Language\ILanguageProvider;
use Dms\Core\Language\Message;
use Dms\Core\Language\MessageNotFoundException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;

/**
 * The laravel language provider.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class LanguageProvider implements ILanguageProvider
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * LaravelLanguageProvider constructor.
     *
     */
    public function __construct()
    {
        $this->translator = new Translator('fr_FR', new MessageSelector());
    }

    /**
     * Gets the fully formed message string from the supplied message id
     * and parameters
     *
     * @param Message $message
     *
     * @return string
     * @throws MessageNotFoundException
     */
    public function format(Message $message) : string
    {
        $namespace = 'dms';

        if ($message->hasNamespace()) {
            $namespace .= '.' . $message->getNamespace();
        }

        $messageId = $namespace . '::' . $message->getId();

        $response = $this->translator->trans(
            $messageId,
            $params = $this->processParameters($message->getParameters())
        );

        // @todo
        // if ($response === $messageId) {
        //     throw MessageNotFoundException::format(
        //         'Could not translate message: unknown message id \'%s\' with params %s',
        //         $messageId, $this->debugFormatParams($params)
        //     );
        // }

        return $response;
    }

    /**
     * Gets the fully formed message strings from the supplied message ids
     * and parameters
     *
     * @param Message[] $messages
     *
     * @return string[]
     * @throws InvalidArgumentException
     * @throws MessageNotFoundException
     */
    public function formatAll(array $messages) : array
    {
        InvalidArgumentException::verifyAllInstanceOf(__METHOD__, 'messages', $messages, Message::class);

        return array_map([$this, 'format'], $messages);
    }

    private function processParameters(array $parameters) : array
    {
        $processedParams = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $processedParams[$key] = implode(', ', $this->processParameters($value));
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $processedParams[$key] = (string)$value;
            } elseif (is_object($value)) {
                $processedParams[$key] = get_class($value);
            } else {
                $processedParams[$key] = (string)$value;
            }
        }

        return $processedParams;
    }

    private function debugFormatParams(array $parameters) : string
    {
        $elements = [];

        foreach ($parameters as $name => $value) {
            $elements[] = $name . ': ' . $value;
        }

        return '[' . implode(', ', $elements) . ']';
    }

    /**
     * @inheritdoc
     */
    public function addResourceDirectory(string $namespace, string $directory)
    {
        $this->translator->addNamespace('dms.' . $namespace, $directory);
    }
}
