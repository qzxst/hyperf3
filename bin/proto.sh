find app/Proto -type f -name "*.proto" -exec sh -c '
  proto_dir=$(dirname "{}")
  relative_dir=$(realpath --relative-to=app/Proto "$proto_dir")
  target_dir="app/Proto/"
  mkdir -p "$target_dir"
  protoc --php_out="$target_dir" --proto_path="$proto_dir" --proto_path=/usr/include  --proto_path=app/Proto "{}"
' \;
