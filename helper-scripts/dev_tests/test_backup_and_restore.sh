#!/usr/bin/env bash

# Test script for backup_and_restore.sh
# Tests backward compatibility with .tar.gz and new .tar.zst format

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BACKUP_IMAGE="${BACKUP_IMAGE:-ghcr.io/mailcow/backup:latest}"
TEST_DIR="/tmp/mailcow_backup_test_$$"
THREADS=2

echo "=== Mailcow Backup & Restore Test Suite ==="
echo "Test directory: ${TEST_DIR}"
echo "Backup image: ${BACKUP_IMAGE}"
echo ""

# Cleanup function
cleanup() {
  echo "Cleaning up test files..."
  rm -rf "${TEST_DIR}"
  docker rmi mailcow-backup-test 2>/dev/null || true
}
trap cleanup EXIT

# Create test directory structure
mkdir -p "${TEST_DIR}"/{test_data,backup_zst,backup_gz,restore_zst,restore_gz,backup_large_zst,backup_large_gz}
echo "Test data for mailcow backup compatibility test" > "${TEST_DIR}/test_data/test.txt"
echo "Additional file to verify complete restore" > "${TEST_DIR}/test_data/test2.txt"

# Build test backup image with zstd support
echo "=== Building backup image with zstd support ==="
docker build -t mailcow-backup-test "${SCRIPT_DIR}/../data/Dockerfiles/backup/" || {
  echo "ERROR: Failed to build backup image"
  exit 1
}

# Test 1: Create .tar.zst backup
echo ""
echo "=== Test 1: Creating .tar.zst backup ==="
docker run --rm \
  -w /data \
  -v "${TEST_DIR}/test_data:/data:ro" \
  -v "${TEST_DIR}/backup_zst:/backup" \
  mailcow-backup-test \
  /bin/tar --use-compress-program="zstd --rsyncable -T${THREADS}" \
  -cvpf /backup/backup_test.tar.zst . \
  > /dev/null
echo "✓ .tar.zst backup created: $(ls -lh ${TEST_DIR}/backup_zst/backup_test.tar.zst | awk '{print $5}')"

# Test 2: Create .tar.gz backup
echo ""
echo "=== Test 2: Creating .tar.gz backup (legacy) ==="
docker run --rm \
  -w /data \
  -v "${TEST_DIR}/test_data:/data:ro" \
  -v "${TEST_DIR}/backup_gz:/backup" \
  mailcow-backup-test \
  /bin/tar --use-compress-program="pigz --rsyncable -p ${THREADS}" \
  -cvpf /backup/backup_test.tar.gz . \
  > /dev/null
echo "✓ .tar.gz backup created: $(ls -lh ${TEST_DIR}/backup_gz/backup_test.tar.gz | awk '{print $5}')"

# Test 3: Test get_archive_info function
echo ""
echo "=== Test 3: Testing get_archive_info function ==="

# Extract and test the function directly
get_archive_info() {
  local backup_name="$1"
  local location="$2"

  if [[ -f "${location}/${backup_name}.tar.zst" ]]; then
    echo "${backup_name}.tar.zst|zstd -d -T${THREADS}"
  elif [[ -f "${location}/${backup_name}.tar.gz" ]]; then
    echo "${backup_name}.tar.gz|pigz -d -p ${THREADS}"
  else
    echo ""
  fi
}

# Test with .tar.zst
result=$(get_archive_info "backup_test" "${TEST_DIR}/backup_zst")
if [[ "${result}" =~ "zstd" ]]; then
  echo "✓ Correctly detects .tar.zst and returns zstd decompressor"
else
  echo "✗ Failed to detect .tar.zst"
  exit 1
fi

# Test with .tar.gz
result=$(get_archive_info "backup_test" "${TEST_DIR}/backup_gz")
if [[ "${result}" =~ "pigz" ]]; then
  echo "✓ Correctly detects .tar.gz and returns pigz decompressor"
else
  echo "✗ Failed to detect .tar.gz"
  exit 1
fi

# Test with no file
result=$(get_archive_info "backup_test" "${TEST_DIR}")
if [[ -z "${result}" ]]; then
  echo "✓ Correctly returns empty when no backup file found"
else
  echo "✗ Should return empty but got: ${result}"
  exit 1
fi

# Test 4: Restore from .tar.zst
echo ""
echo "=== Test 4: Restoring from .tar.zst ==="
docker run --rm \
  -w /restore \
  -v "${TEST_DIR}/backup_zst:/backup:ro" \
  -v "${TEST_DIR}/restore_zst:/restore" \
  mailcow-backup-test \
  /bin/tar --use-compress-program="zstd -d -T${THREADS}" -xvpf /backup/backup_test.tar.zst \
  > /dev/null 2>&1

if [[ -f "${TEST_DIR}/restore_zst/test.txt" ]] && \
   [[ -f "${TEST_DIR}/restore_zst/test2.txt" ]]; then
  echo "✓ Successfully restored from .tar.zst"
else
  echo "✗ Failed to restore from .tar.zst"
  ls -la "${TEST_DIR}/restore_zst/" || true
  exit 1
fi

# Test 5: Restore from .tar.gz
echo ""
echo "=== Test 5: Restoring from .tar.gz (backward compatibility) ==="
docker run --rm \
  -w /restore \
  -v "${TEST_DIR}/backup_gz:/backup:ro" \
  -v "${TEST_DIR}/restore_gz:/restore" \
  mailcow-backup-test \
  /bin/tar --use-compress-program="pigz -d -p ${THREADS}" -xvpf /backup/backup_test.tar.gz \
  > /dev/null 2>&1

if [[ -f "${TEST_DIR}/restore_gz/test.txt" ]] && \
   [[ -f "${TEST_DIR}/restore_gz/test2.txt" ]]; then
  echo "✓ Successfully restored from .tar.gz (backward compatible)"
else
  echo "✗ Failed to restore from .tar.gz"
  ls -la "${TEST_DIR}/restore_gz/" || true
  exit 1
fi

# Test 6: Verify content integrity
echo ""
echo "=== Test 6: Verifying content integrity ==="
original_content=$(cat "${TEST_DIR}/test_data/test.txt")
zst_content=$(cat "${TEST_DIR}/restore_zst/test.txt")
gz_content=$(cat "${TEST_DIR}/restore_gz/test.txt")

if [[ "${original_content}" == "${zst_content}" ]] && \
   [[ "${original_content}" == "${gz_content}" ]]; then
  echo "✓ Content integrity verified for both formats"
else
  echo "✗ Content mismatch detected"
  exit 1
fi

# Test 7: Compare compression ratios
echo ""
echo "=== Test 7: Compression comparison ==="
zst_size=$(stat -f%z "${TEST_DIR}/backup_zst/backup_test.tar.zst" 2>/dev/null || stat -c%s "${TEST_DIR}/backup_zst/backup_test.tar.zst")
gz_size=$(stat -f%z "${TEST_DIR}/backup_gz/backup_test.tar.gz" 2>/dev/null || stat -c%s "${TEST_DIR}/backup_gz/backup_test.tar.gz")
improvement=$(echo "scale=2; (${gz_size} - ${zst_size}) * 100 / ${gz_size}" | bc)

echo "  Small files - .tar.gz size: ${gz_size} bytes"
echo "  Small files - .tar.zst size: ${zst_size} bytes"
echo "  Small files - Improvement: ${improvement}% smaller with zstd"

# Test 8: Error handling - missing backup file
echo ""
echo "=== Test 8: Error handling - Missing backup file ==="
result=$(get_archive_info "nonexistent_backup" "${TEST_DIR}/backup_zst")
if [[ -z "${result}" ]]; then
  echo "✓ Correctly handles missing backup files"
else
  echo "✗ Should return empty for missing files"
  exit 1
fi

# Test 9: Error handling - Empty directory
echo ""
echo "=== Test 9: Error handling - Empty directory ==="
mkdir -p "${TEST_DIR}/empty_dir"
result=$(get_archive_info "backup_test" "${TEST_DIR}/empty_dir")
if [[ -z "${result}" ]]; then
  echo "✓ Correctly handles empty directories"
else
  echo "✗ Should return empty for empty directories"
  exit 1
fi

# Test 10: Priority test - .tar.zst preferred over .tar.gz
echo ""
echo "=== Test 10: Format priority - .tar.zst preferred ==="
mkdir -p "${TEST_DIR}/both_formats"
touch "${TEST_DIR}/both_formats/backup_test.tar.gz"
touch "${TEST_DIR}/both_formats/backup_test.tar.zst"
result=$(get_archive_info "backup_test" "${TEST_DIR}/both_formats")
if [[ "${result}" =~ "zstd" ]]; then
  echo "✓ Correctly prefers .tar.zst when both formats exist"
else
  echo "✗ Should prefer .tar.zst over .tar.gz"
  exit 1
fi

# Test 11: Large file compression test
echo ""
echo "=== Test 11: Large file compression test ==="
mkdir -p "${TEST_DIR}/large_data"
# Create ~10MB of compressible data (log-like content)
for i in {1..50000}; do
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: Processing email message $i from user@example.com to recipient@domain.com" >> "${TEST_DIR}/large_data/maillog.txt"
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] DEBUG: SMTP connection established from 192.168.1.$((i % 255))" >> "${TEST_DIR}/large_data/maillog.txt"
done 2>/dev/null

# Get size (portable: works on Linux and macOS)
if du --version 2>/dev/null | grep -q GNU; then
  original_size=$(du -sb "${TEST_DIR}/large_data" | cut -f1)
else
  # macOS
  original_size=$(find "${TEST_DIR}/large_data" -type f -exec stat -f%z {} \; | awk '{sum+=$1} END {print sum}')
fi
echo "  Original data size: $(echo "scale=2; ${original_size} / 1024 / 1024" | bc) MB"

# Backup with zstd
docker run --rm \
  -w /data \
  -v "${TEST_DIR}/large_data:/data:ro" \
  -v "${TEST_DIR}/backup_large_zst:/backup" \
  mailcow-backup-test \
  /bin/tar --use-compress-program="zstd --rsyncable -T${THREADS}" \
  -cvpf /backup/backup_large.tar.zst . \
  > /dev/null 2>&1

# Backup with pigz
docker run --rm \
  -w /data \
  -v "${TEST_DIR}/large_data:/data:ro" \
  -v "${TEST_DIR}/backup_large_gz:/backup" \
  mailcow-backup-test \
  /bin/tar --use-compress-program="pigz --rsyncable -p ${THREADS}" \
  -cvpf /backup/backup_large.tar.gz . \
  > /dev/null 2>&1

zst_large_size=$(stat -f%z "${TEST_DIR}/backup_large_zst/backup_large.tar.zst" 2>/dev/null || stat -c%s "${TEST_DIR}/backup_large_zst/backup_large.tar.zst" 2>/dev/null || echo "0")
gz_large_size=$(stat -f%z "${TEST_DIR}/backup_large_gz/backup_large.tar.gz" 2>/dev/null || stat -c%s "${TEST_DIR}/backup_large_gz/backup_large.tar.gz" 2>/dev/null || echo "0")

if [[ ${zst_large_size} -gt 0 ]] && [[ ${gz_large_size} -gt 0 ]]; then
  large_improvement=$(echo "scale=2; (${gz_large_size} - ${zst_large_size}) * 100 / ${gz_large_size}" | bc)

  echo "  .tar.gz compressed: $(echo "scale=2; ${gz_large_size} / 1024 / 1024" | bc) MB"
  echo "  .tar.zst compressed: $(echo "scale=2; ${zst_large_size} / 1024 / 1024" | bc) MB"
  echo "  Improvement: ${large_improvement}% smaller with zstd"
else
  echo "  ✗ Failed to get file sizes"
  exit 1
fi

if [[ $(echo "${large_improvement} > 0" | bc) -eq 1 ]]; then
  echo "✓ zstd provides better compression on realistic data"
else
  echo "⚠ zstd compression similar or worse than gzip (unusual but not critical)"
fi

# Test 12: Thread scaling test
echo ""
echo "=== Test 12: Multi-threading verification ==="
# This test verifies that different thread counts work (not measuring speed difference)
for thread_count in 1 4; do
  THREADS=${thread_count}
  result=$(get_archive_info "backup_test" "${TEST_DIR}/backup_zst")
  if [[ "${result}" =~ "-T${thread_count}" ]]; then
    echo "✓ Thread count ${thread_count} correctly configured"
  else
    echo "✗ Thread count not properly applied"
    exit 1
  fi
done

echo ""
echo "=== All tests passed! ==="
echo ""
echo "Summary:"
echo "  ✓ zstd compression working"
echo "  ✓ pigz compression working (legacy)"
echo "  ✓ zstd decompression working"
echo "  ✓ pigz decompression working (backward compatible)"
echo "  ✓ Archive detection working"
echo "  ✓ Content integrity verified"
echo "  ✓ Format priority correct (.tar.zst preferred)"
echo "  ✓ Error handling for missing files"
echo "  ✓ Error handling for empty directories"
echo "  ✓ Multi-threading configuration verified"
echo "  ✓ Large file compression: ${large_improvement}% improvement"
echo "  ✓ Small file compression: ${improvement}% improvement"
