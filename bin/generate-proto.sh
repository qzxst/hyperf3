#!/bin/bash

# Hyperf gRPC Protobuf 生成脚本
# 版本: 1.1
# 作者: Hyperf 游戏后端团队

# 配置参数
PROTO_DIR="app/Proto"
OUTPUT_DIR="app/Service/GPBMetadata"
GRPC_OUTPUT_DIR="app/Service/RequestResponse"
PROTOC_PATH="/usr/local/bin/protoc"  # 修改为您的 protoc 路径
GRPC_PLUGIN_PATH="/usr/bin/grpc_php_plugin"  # 修改为您的插件路径

# 检查 protoc 是否安装
if ! command -v $PROTOC_PATH &> /dev/null; then
    echo "错误: protoc 未安装或路径配置错误"
    echo "请先安装 protoc: https://grpc.io/docs/protoc-installation/"
    exit 1
fi

# 检查 grpc_php_plugin 是否安装
if ! command -v $GRPC_PLUGIN_PATH &> /dev/null; then
    echo "错误: grpc_php_plugin 未安装或路径配置错误"
    echo "请安装 gRPC PHP 插件: https://github.com/grpc/grpc/blob/v1.54.0/src/php/README.md"
    exit 1
fi

# 清理旧文件
echo "清理旧生成文件..."
rm -rf $OUTPUT_DIR
rm -rf $GRPC_OUTPUT_DIR

# 创建目录
mkdir -p $OUTPUT_DIR
mkdir -p $GRPC_OUTPUT_DIR

# 遍历所有 proto 文件
for proto_file in $PROTO_DIR/*.proto; do
    if [ ! -f "$proto_file" ]; then
        echo "警告: 在 $PROTO_DIR 目录中未找到 .proto 文件"
        continue
    fi

    filename=$(basename -- "$proto_file")
    filename_noext="${filename%.*}"

    echo "处理文件: $filename"

    # 生成 PHP 代码
    $PROTOC_PATH \
        --php_out=$OUTPUT_DIR \
        --grpc_out=$GRPC_OUTPUT_DIR \
        --plugin=protoc-gen-grpc=$GRPC_PLUGIN_PATH \
        -I $PROTO_DIR \
        $proto_file

    # 检查命令执行结果
    if [ $? -ne 0 ]; then
        echo "错误: 生成 $filename 失败"
        exit 2
    fi
done
