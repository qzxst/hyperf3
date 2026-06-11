<?php
// app/Utils/MsgPackHelper.php
declare(strict_types=1);

namespace App\Utils;

use MessagePack\MessagePack;
use MessagePack\PackOptions;
use MessagePack\UnpackOptions;

class MsgPackHelper
{
    /**
     * 编码 Protobuf 消息为 MsgPack 格式
     */
    public static function encodeProtoMessage($protoMessage): string
    {
        // 先将 Protobuf 消息序列化为二进制
        $protoData = $protoMessage->serializeToString();
        // return $protoData;

        echo "Serialized Protobuf data length: " . strlen($protoData) . "\n";
        echo "Serialized Protobuf data (hex): " . bin2hex($protoData) . "\n";
        echo "Serialized Protobuf data (base64): " . base64_encode($protoData) . "\n";
        echo "Protobuf Message Type: " . get_class($protoMessage) . "\n";
        echo "Protobuf Message Data: " . $protoData . "\n";
        // 创建包含类型信息和数据的数组
        $messageData = [
            'type' => get_class($protoMessage),
            'data' => $protoData
        ];

        // 使用 MsgPack 编码
        return MessagePack::pack($messageData, PackOptions::FORCE_STR);
    }

    /**
     * 解码 MsgPack 数据为 Protobuf 消息
     */
    public static function decodeProtoMessage(string $msgpackData, string $expectedType = null)
    {
        // 使用 MsgPack 解码
        $decodedData = MessagePack::unpack($msgpackData, UnpackOptions::BIGINT_AS_STR);

        if (!isset($decodedData['type']) || !isset($decodedData['data'])) {
            throw new \InvalidArgumentException('Invalid MsgPack format');
        }

        $messageType = $decodedData['type'];
        $protoData = $decodedData['data'];

        // 验证类型（如果指定了期望类型）
        if ($expectedType && $messageType !== $expectedType) {
            throw new \InvalidArgumentException("Expected type {$expectedType}, got {$messageType}");
        }

        // 创建 Protobuf 消息实例并反序列化
        if (!class_exists($messageType)) {
            throw new \InvalidArgumentException("Class {$messageType} does not exist");
        }
        // $messageType = 'slot.ClientMessage';
        // $protoData = $msgpackData;

        $message = new $messageType();
        $message->mergeFromString($protoData);

        return $message;
    }

    /**
     * 编码任意数据为 MsgPack 格式
     */
    public static function encode($data): string
    {
        return MessagePack::pack($data, PackOptions::FORCE_STR);
    }

    /**
     * 解码 MsgPack 数据
     */
    public static function decode(string $msgpackData)
    {
        return MessagePack::unpack($msgpackData, UnpackOptions::BIGINT_AS_STR);
    }
}
