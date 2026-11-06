/*
 * Spike, stress and soak need the owner's approval for each run. The
 * operator states it by setting QE_HEAVY_APPROVAL to the run's own QE_RUN_ID
 * after recording the approval in 17-execution-log.md; anything else stops
 * the script in the init stage, before any request.
 */
export function requireHeavyApproval(target, script) {
    if (__ENV.QE_HEAVY_APPROVAL !== target.runId) {
        throw new Error(
            `QE guard refused ${script}: set QE_HEAVY_APPROVAL to this QE_RUN_ID after the owner approved this run`,
        );
    }
}
