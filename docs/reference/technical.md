# Technical documentation


## Configuration

Pelf's configuration is stored JSON-encoded in a `pelf_config` setting.
For details of this structure see the `pelfConfig` key from the
Pelf.getconfig API (see below).

## APIv3: `Pelf.getconfig`

Returns the settings and other metadata from CiviCRM.

```json
{
  pelfConfig: {
    caseTypes: [<case_type_name>, ...],
    statusMeta: {
      <case_status_name>: {
        phase: 'prospect|contract|failed|dropped|completed',
      }
    }
  },
  caseStatusesInUse: [
    { id, name, label, color, grouping },
    ...
  ],
}
```

The `phase` groups status options with the following meanings:

- `prospect` - this status is used while actively pursuing this venture.
  e.g. the following statuses might be used in this phase: research,
  approach, negotiation.

- `live` - we won, and these statuses are used during the management
  of the contract. e.g. 'live contract', 'evaluation', 'final report'
  - whatever is needed (may only need one)

- `completed` - we won funds, spent them, and it's all wrapped up.

- `failed` - efforts to get funds were unsuccessful; they turned us down.

- `dropped` - we stopped the process, e.g. having researched the prospect
  we decided it was not suitable.

`caseStatusesInUse` is the list of case statuses used by case types that
belong to Pelf.
