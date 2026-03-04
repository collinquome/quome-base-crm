export interface Contact {
  id: number;
  name: string;
  emails?: Array<{ value: string; label: string }>;
  contact_numbers?: Array<{ value: string; label: string }>;
  organization_id?: number;
  organization?: Organization;
}

export interface Organization {
  id: number;
  name: string;
  address?: string;
}

export interface Lead {
  id: number;
  title: string;
  description?: string;
  lead_value?: number;
  status: number;
  person_id?: number;
  person?: Contact;
  lead_pipeline_id: number;
  lead_pipeline_stage_id: number;
  pipeline?: Pipeline;
  stage?: PipelineStage;
}

export interface Pipeline {
  id: number;
  name: string;
  is_default: boolean;
  stages: PipelineStage[];
}

export interface PipelineStage {
  id: number;
  name: string;
  code: string;
  sort_order: number;
  probability: number;
}

export interface Activity {
  id: number;
  title: string;
  type: 'call' | 'meeting' | 'task' | 'note' | 'email';
  description?: string;
  schedule_from?: string;
  schedule_to?: string;
  is_done: boolean;
  user_id: number;
}

export interface NextAction {
  id: number;
  actionable_type: string;
  actionable_id: number;
  action_type: string;
  description: string;
  due_date?: string;
  due_time?: string;
  priority: 'urgent' | 'high' | 'normal' | 'low';
  status: 'pending' | 'completed' | 'snoozed';
}

export interface Notification {
  id: number;
  type: string;
  title: string;
  body: string;
  data?: Record<string, unknown>;
  read_at?: string;
  created_at: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
