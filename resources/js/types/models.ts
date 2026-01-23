// User types
export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at?: string;
    two_factor_enabled?: boolean;
    servers_count?: number;
    created_at: string;
}

// Location types
export interface Location {
    id: number;
    name: string;
    short_code: string;
    description?: string;
    nodes_count?: number;
    created_at: string;
    updated_at: string;
}

// Node types
export interface Node {
    id: number;
    uuid: string;
    name: string;
    fqdn: string;
    port: number;
    cluster?: string;
    storage: string;
    network: string;
    memory: number;
    memory_overallocate: number;
    disk: number;
    disk_overallocate: number;
    cpu: number;
    cpu_overallocate: number;
    maintenance_mode: boolean;
    servers_count?: number;
    location?: LocationBrief;
    created_at: string;
    updated_at: string;
}

export interface LocationBrief {
    id: number;
    name: string;
    short_code: string;
}

export interface NodeStats {
    uptime: number;
    cpu: {
        usage: number;
        cores: number;
        model: string;
    };
    memory: {
        used: number;
        total: number;
        free: number;
        usage: number;
    };
    disk: {
        used: number;
        total: number;
        free: number;
        usage: number;
    };
}

// Server types
export interface Server {
    id: number;
    uuid: string;
    vmid: string;
    name: string;
    hostname?: string;
    description?: string;
    status: 'installing' | 'rebuilding' | 'running' | 'stopped' | 'suspended' | 'pending' | 'failed';
    is_suspended: boolean;
    is_installing: boolean;
    installation_task?: string;
    cpu: number;
    memory: number;
    memory_formatted: string;
    disk: number;
    disk_formatted: string;
    bandwidth_limit: number | null;
    bandwidth_usage: number;
    node_id: number;
    user?: UserBrief;
    node?: NodeBrief;
    addresses?: Address[];
    installed_at?: string;
    created_at: string;
}

export interface UserBrief {
    id: number;
    name: string;
    email: string;
}

export interface NodeBrief {
    id: number;
    name: string;
    location?: LocationBrief;
}

export interface ServerStatus {
    status: string;
    uptime: number;
    cpu: number;
    maxcpu?: number;
    mem?: number;
    maxmem?: number;
    memory: {
        used: number;
        total: number;
        percentage?: number;
    };
    disk?: number;
    maxdisk?: number;
    netin?: number;
    netout?: number;
    agent?: boolean;
}

// Address types
export interface Address {
    id: number;
    address: string;
    cidr: number;
    gateway: string;
    type: 'ipv4' | 'ipv6';
    mac_address?: string;
    is_primary: boolean;
    server?: ServerBrief;
}

export interface ServerBrief {
    id: number;
    uuid: string;
    name: string;
}

export interface AddressPool {
    id: number;
    name: string;
    addresses_count: number;
    available_count: number;
    nodes: { id: number; name: string }[];
    addresses?: Address[];
    created_at: string;
}

// Template types
export interface Template {
    id: number;
    uuid: string;
    name: string;
    vmid: string;
    order: number;
    visible: boolean;
}

export interface TemplateGroup {
    id: number;
    node_id: number;
    name: string;
    order: number;
    visible: boolean;
    templates_count: number;
    templates: Template[];
}

// API response types
export interface ApiResponse<T> {
    data: T;
    message?: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

// Auth types
export interface LoginResponse {
    message: string;
    token: string;
    user: User;
}
