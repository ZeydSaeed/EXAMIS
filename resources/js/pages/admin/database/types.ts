export type DatabaseColumn = {
    name: string;
    type: string;
    kind:
        | 'text'
        | 'integer'
        | 'decimal'
        | 'boolean'
        | 'json'
        | 'datetime'
        | 'date'
        | 'binary';
    nullable: boolean;
    has_default: boolean;
    auto: boolean;
    secret: boolean;
    key: boolean;
};

export type DatabaseTable = {
    name: string;
    label: string;
    writable: boolean;
    insertable: boolean;
};
