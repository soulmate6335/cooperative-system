import type { ReactNode } from 'react'
import PeopleAltIcon from '@mui/icons-material/PeopleAlt'

import { PageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'

export function MembersDirectoryPage(): ReactNode {
  return (
    <PageContainer title="Members Directory" subtitle="Browse registered cooperative members">
      <EmptyState
        icon={PeopleAltIcon}
        title="Members directory is not available yet"
        description="The member directory requires a dedicated directory endpoint which has not been enabled. Members are managed through registration and membership applications."
      />
    </PageContainer>
  )
}