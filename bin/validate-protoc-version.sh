# bin/validate-protoc-version.sh
#!/bin/bash
REQUIRED_VERSION=$(grep 'protoc_version=' .protoc-version | cut -d'=' -f2)
CURRENT_VERSION=$(protoc --version | awk '{print $2}')

if [ "$CURRENT_VERSION" != "$REQUIRED_VERSION" ]; then
    echo >&2 "错误: 需要 protoc v$REQUIRED_VERSION (当前: v$CURRENT_VERSION)"
    echo >&2 "请运行: ./bin/install-protoc.sh"
    exit 1
fi
