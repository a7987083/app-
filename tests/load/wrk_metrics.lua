done = function(summary, latency, requests)
    local errors = 0
    if summary.errors then
        errors = (summary.errors.connect or 0)
            + (summary.errors.read or 0)
            + (summary.errors.write or 0)
            + (summary.errors.status or 0)
            + (summary.errors.timeout or 0)
    end
    io.write(string.format(
        "ZONOE_WRK_RESULT requests=%d duration_us=%d bytes=%d p50_us=%d p95_us=%d p99_us=%d errors=%d\n",
        summary.requests or 0,
        summary.duration or 0,
        summary.bytes or 0,
        latency:percentile(50.0),
        latency:percentile(95.0),
        latency:percentile(99.0),
        errors
    ))
end
